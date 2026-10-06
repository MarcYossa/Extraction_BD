<?php

namespace App\Services\Extraction;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Service de lecture et écriture atomique du catalogue d'extractions (queries.json).
 */
class CatalogueRepository
{
    protected string $relativePath;

    public function __construct()
    {
        $this->relativePath = config('extraction.queries_file', 'extraction/queries.json');
    }

    /**
     * Lit la totalité du catalogue depuis le fichier storage.
     */
    public function loadRaw(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($this->relativePath)) {
            return [
                'schema_version' => 1,
                'modifie_le'     => now()->toIso8601String(),
                'extractions'    => [],
            ];
        }

        $content = $disk->get($this->relativePath);
        $data = json_decode($content, true);

        return is_array($data) ? $data : [
            'schema_version' => 1,
            'modifie_le'     => now()->toIso8601String(),
            'extractions'    => [],
        ];
    }

    /**
     * Retourne la liste de toutes les extractions du catalogue.
     *
     * @return array<int, array>
     */
    public function all(): array
    {
        $raw = $this->loadRaw();
        return $raw['extractions'] ?? [];
    }

    /**
     * Recherche une extraction par son code définitif (ex: CPT_001).
     */
    public function find(string $code): ?array
    {
        foreach ($this->all() as $extraction) {
            if (strtoupper($extraction['code'] ?? '') === strtoupper($code)) {
                return $extraction;
            }
        }

        return null;
    }

    /**
     * Retourne uniquement les extractions au statut 'active' (pour l'espace métier).
     *
     * @return array<int, array>
     */
    public function actives(): array
    {
        return array_values(array_filter($this->all(), function (array $ext) {
            return ($ext['statut'] ?? '') === 'active';
        }));
    }

    /**
     * Enregistre ou met à jour une extraction de manière atomique (verrou + fichier temporaire).
     */
    public function save(array $extraction, string $username = 'system'): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($this->relativePath));
        $fullPath = $disk->path($this->relativePath);
        $tempPath = $fullPath . '.tmp.' . uniqid('', true);

        // Verrou global via un fichier de verrou séparé ou le fichier lui-même
        $lockFile = $fullPath . '.lock';
        $lockHandle = fopen($lockFile, 'c+');
        if (! $lockHandle) {
            throw new RuntimeException("Impossible d'ouvrir le fichier de verrou : {$lockFile}");
        }

        try {
            flock($lockHandle, LOCK_EX);

            $raw = $this->loadRaw();
            $extractions = $raw['extractions'] ?? [];

            $nowIso = now()->toIso8601String();
            $codeTarget = strtoupper($extraction['code'] ?? '');
            $found = false;

            foreach ($extractions as $i => $item) {
                if (strtoupper($item['code'] ?? '') === $codeTarget) {
                    $extraction['modifie_le']  = $nowIso;
                    $extraction['modifie_par'] = $username;
                    // Préserver la date de création initiale si absente
                    $extraction['cree_le']     = $item['cree_le'] ?? $nowIso;
                    $extraction['cree_par']    = $item['cree_par'] ?? $username;
                    $extractions[$i]           = $extraction;
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $extraction['cree_le']     = $extraction['cree_le'] ?? $nowIso;
                $extraction['cree_par']    = $extraction['cree_par'] ?? $username;
                $extraction['modifie_le']  = $nowIso;
                $extraction['modifie_par'] = $username;
                $extractions[]            = $extraction;
            }

            $raw['schema_version'] = 1;
            $raw['modifie_le']     = $nowIso;
            $raw['extractions']    = array_values($extractions);

            $jsonContent = json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($tempPath, $jsonContent) === false) {
                throw new RuntimeException("Impossible d'écrire le fichier temporaire du catalogue.");
            }

            // Remplacement atomique
            rename($tempPath, $fullPath);

            flock($lockHandle, LOCK_UN);
        } finally {
            fclose($lockHandle);
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }
}
