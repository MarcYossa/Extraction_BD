<?php

namespace App\Services\Extraction;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service d'export CSV pour les résultats d'extraction conservés dans ResultStore.
 *
 * Spécifications :
 *  - Encodage UTF-8 avec BOM (\xEF\xBB\xBF) pour compatibilité Excel
 *  - Séparateur point-virgule (;)
 *  - En-têtes : libellés des colonnes
 *  - Formatage : dates jj/mm/aaaa, nombres bruts
 *  - Neutralisation des formules Excel (=, +, -, @, \t, \r)
 *  - Nom de fichier : CODE_AAAAMMJJ_HHMM.csv
 */
class CsvExporter
{
    public function __construct(
        protected ColumnFormatter $formatter = new ColumnFormatter(),
    ) {}

    /**
     * Génère la réponse de téléchargement en streaming du fichier CSV.
     */
    public function download(array $resultat, ?string $filename = null): StreamedResponse
    {
        $code = $resultat['code'] ?? 'EXTRACTION';
        $filename = $filename ?? sprintf('%s_%s.csv', $code, now()->format('Ymd_Hi'));

        $colonnes = $resultat['colonnes'] ?? [];
        $rows = $resultat['raw_lignes'] ?? $resultat['lignes'] ?? [];

        // Préparation des clés et libellés d'en-tête
        $keys = [];
        $headers = [];
        foreach ($colonnes as $col) {
            $keys[] = strtolower($col['cle'] ?? '');
            $headers[] = $col['libelle'] ?? $col['cle'] ?? '';
        }

        // Si aucune colonne déclarée mais que des lignes existent
        if (empty($keys) && ! empty($rows)) {
            $first = array_change_key_case((array) $rows[0], CASE_LOWER);
            $keys = array_keys($first);
            $headers = array_map('strtoupper', $keys);
        }

        return new StreamedResponse(function () use ($rows, $colonnes, $keys, $headers) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 : indispensable pour les accents dans Excel
            fwrite($out, "\xEF\xBB\xBF");

            // Ligne d'en-têtes
            fputcsv($out, $headers, ';', '"', '\\');

            // Lignes de données
            foreach ($rows as $rawRow) {
                $rawRow = array_change_key_case((array) $rawRow, CASE_LOWER);
                $formattedRow = $this->formatter->formatRow($rawRow, $colonnes, true);

                $line = [];
                foreach ($keys as $k) {
                    $val = $formattedRow[$k] ?? null;
                    $line[] = $this->csvSafe($val);
                }
                fputcsv($out, $line, ';', '"', '\\');
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);

    }

    /**
     * Neutralise les formules Excel (injection CSV) : =, +, -, @, \t, \r en début de cellule.
     */
    public function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
