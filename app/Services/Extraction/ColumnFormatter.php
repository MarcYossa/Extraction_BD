<?php

namespace App\Services\Extraction;

use Carbon\Carbon;

/**
 * Service de mise en forme des colonnes pour l'affichage écran et l'export CSV.
 */
class ColumnFormatter
{
    /**
     * Formate une ligne de résultats selon la déclaration des colonnes.
     *
     * @param array<string, mixed> $row Ligne brute issue de la base de données
     * @param array<int, array> $declaredColumns Liste des colonnes déclarées dans l'extraction
     * @param bool $isCsv Vrai si le formatage est destiné à l'export CSV
     * @return array<string, mixed> Ligne formatée
     */
    public function formatRow(array $row, array $declaredColumns = [], bool $isCsv = false): array
    {
        $colsByKey = [];
        foreach ($declaredColumns as $col) {
            if (isset($col['cle'])) {
                $colsByKey[strtolower($col['cle'])] = $col;
            }
        }

        $formatted = [];
        foreach ($row as $key => $val) {
            $lowerKey = strtolower($key);
            $format = $colsByKey[$lowerKey]['format'] ?? 'texte';
            $formatted[$key] = $this->formatValue($val, $format, $isCsv);
        }

        return $formatted;
    }

    /**
     * Formate une valeur individuelle selon le format spécifié.
     */
    public function formatValue(mixed $value, string $format, bool $isCsv = false): mixed
    {
        if ($value === null || $value === '') {
            return $isCsv ? '' : null;
        }

        switch ($format) {
            case 'date':
                try {
                    $d = Carbon::parse($value);
                    return $d->format('d/m/Y');
                } catch (\Throwable $e) {
                    return (string) $value;
                }

            case 'nombre':
                if (! is_numeric($value)) {
                    return (string) $value;
                }
                $num = $value + 0;
                if ($isCsv) {
                    return $num;
                }
                return is_float($num)
                    ? number_format($num, 2, ',', ' ')
                    : number_format($num, 0, ',', ' ');

            case 'montant':
                if (! is_numeric($value)) {
                    return (string) $value;
                }
                $num = (float) $value;
                if ($isCsv) {
                    return $num;
                }
                return number_format($num, 2, ',', ' ');

            case 'code':
            case 'statut':
            case 'texte':
            default:
                return (string) $value;
        }
    }
}
