<?php

namespace App\Services\Extraction;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service de conservation temporaire des résultats d'extractions (D13).
 * Conserve le résultat dans storage/app/private/results/{jeton}.json pendant 15 minutes,
 * lié à l'utilisateur ayant exécuté la requête.
 */
class ResultStore
{
    protected Filesystem $disk;
    protected string $directory;
    protected int $lifetimeMinutes;
    protected bool $opportunisticPurge = true;

    public function __construct(?Filesystem $disk = null)
    {
        $this->disk = $disk ?? Storage::disk('local');
        $this->directory = (string) config('extraction.results_dir', 'results');
        $this->lifetimeMinutes = (int) config('extraction.results_lifetime_minutes', 15);
    }

    public function setOpportunisticPurge(bool $enabled): self
    {
        $this->opportunisticPurge = $enabled;
        return $this;
    }

    /**
     * Enregistre le résultat d'une exécution et retourne son jeton de 40 caractères.
     */
    public function store(
        string $utilisateur,
        string $code,
        int $version,
        array $parametres,
        array $colonnes,
        array $lignes,
        int $dureeMs,
        bool $tronque = false,
        ?int $totalLignes = null,
        array $parametresAffiches = []
    ): string {
        $jeton = Str::random(40);
        $now = now();
        $expireLe = $now->copy()->addMinutes($this->lifetimeMinutes);

        $data = [
            'jeton'               => $jeton,
            'utilisateur'         => $utilisateur,
            'code'                => $code,
            'version'             => $version,
            'cree_le'             => $now->toIso8601String(),
            'expire_le'           => $expireLe->toIso8601String(),
            'parametres'          => $parametres,
            'parametres_affiches' => $parametresAffiches,
            'colonnes'            => $colonnes,
            'lignes'              => $lignes,
            'total_lignes'        => $totalLignes ?? count($lignes),
            'nb_lignes'           => count($lignes),
            'duree_ms'            => $dureeMs,
            'tronque'             => $tronque,
        ];

        $this->disk->makeDirectory($this->directory);
        $path = $this->pathFor($jeton);
        $this->disk->put($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Purge opportuniste (1 chance sur 10)
        if ($this->opportunisticPurge && random_int(1, 10) === 1) {
            $this->purgeExpired();
        }

        return $jeton;
    }

    /**
     * Récupère le résultat si le jeton existe, n'est pas expiré et appartient à l'utilisateur.
     * Renvoie null sinon (entraînant un 404 côté contrôleur).
     */
    public function get(string $jeton, string $utilisateur): ?array
    {
        if (! preg_match('/^[a-zA-Z0-9]{40}$/', $jeton)) {
            return null;
        }

        $path = $this->pathFor($jeton);
        if (! $this->disk->exists($path)) {
            return null;
        }

        $content = $this->disk->get($path);
        $data = json_decode((string) $content, true);

        if (! is_array($data) || ! isset($data['utilisateur'], $data['expire_le'])) {
            $this->disk->delete($path);
            return null;
        }

        // Vérification de l'utilisateur
        if ($data['utilisateur'] !== $utilisateur) {
            return null;
        }

        // Vérification de l'expiration
        $expireLe = Carbon::parse($data['expire_le']);
        if (now()->greaterThanOrEqualTo($expireLe)) {
            $this->disk->delete($path);
            return null;
        }

        return $data;
    }

    /**
     * Supprime manuellement un résultat.
     */
    public function delete(string $jeton): bool
    {
        $path = $this->pathFor($jeton);
        if ($this->disk->exists($path)) {
            return $this->disk->delete($path);
        }
        return false;
    }

    /**
     * Purge tous les fichiers de résultats expirés.
     * Retourne le nombre de fichiers supprimés.
     */
    public function purgeExpired(): int
    {
        if (! $this->disk->exists($this->directory)) {
            return 0;
        }

        $files = $this->disk->files($this->directory);
        $deleted = 0;
        $now = now();

        foreach ($files as $file) {
            if (! str_ends_with($file, '.json')) {
                continue;
            }

            try {
                $content = $this->disk->get($file);
                $data = json_decode((string) $content, true);

                if (! is_array($data) || ! isset($data['expire_le'])) {
                    $this->disk->delete($file);
                    $deleted++;
                    continue;
                }

                $expireLe = Carbon::parse($data['expire_le']);
                if ($now->greaterThanOrEqualTo($expireLe)) {
                    $this->disk->delete($file);
                    $deleted++;
                }
            } catch (\Throwable $e) {
                // En cas de fichier illisible ou corrompu
                $this->disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Chemin relatif dans le disque de stockage.
     */
    protected function pathFor(string $jeton): string
    {
        return $this->directory . '/' . $jeton . '.json';
    }
}
