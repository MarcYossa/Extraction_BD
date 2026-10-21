<?php

namespace App\Services\Extraction;

use Illuminate\Support\Facades\Storage;

/**
 * Journal des exécutions d'extractions (storage/app/private/extraction/execution_log.json).
 * Un verrou de fichier évite la corruption lors des écritures simultanées.
 */
class ExecutionLogger
{
    protected string $relativePath;

    public function __construct()
    {
        $this->relativePath = config('extraction.log_file', 'extraction/execution_log.json');
    }

    /**
     * Journalise une exécution avec les métadonnées requises.
     *
     * @param string $utilisateur Identifiant de l'utilisateur (ex: amine.m, sonia.d)
     * @param string $code Code de l'extraction (ex: CPT_001)
     * @param int $version Numéro de version de l'extraction
     * @param string $mode 'test' | 'execution' | 'export_csv'
     * @param array $parametres Paramètres utilisés pour l'exécution
     * @param string $statut 'succes' | 'echec'
     * @param int $nbLignes Nombre de lignes retournées
     * @param bool $tronque Vrai si le résultat a atteint le plafond
     * @param int $dureeMs Durée d'exécution en millisecondes
     */
    public function logExecution(
        string $utilisateur,
        string $code,
        int $version,
        string $mode,
        array $parametres,
        string $statut,
        int $nbLignes,
        bool $tronque,
        int $dureeMs
    ): void {
        $this->record([
            'utilisateur' => $utilisateur,
            'code'        => $code,
            'version'     => $version,
            'mode'        => $mode,
            'parametres'  => $parametres,
            'statut'      => $statut,
            'nb_lignes'   => $nbLignes,
            'tronque'     => $tronque,
            'duree_ms'    => $dureeMs,
        ]);
    }

    /**
     * Enregistre une entrée générique dans le fichier de journalisation.
     */
    public function record(array $entry): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($this->relativePath));
        $fullPath = $disk->path($this->relativePath);

        $formattedEntry = [
            'date' => now()->toIso8601String(),
        ] + $entry;

        $handle = fopen($fullPath, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            flock($handle, LOCK_EX);
            $content = stream_get_contents($handle);
            $log = json_decode($content === false || $content === '' ? '[]' : $content, true);
            if (! is_array($log)) {
                $log = [];
            }

            $log[] = $formattedEntry;

            // Protection volumétrie : si le journal dépasse 25 000 entrées, conserver les 20 000 plus récentes
            if (count($log) > 25000) {
                $log = array_slice($log, -20000);
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }
}
