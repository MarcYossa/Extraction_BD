<?php

namespace App\Services\Extraction;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service fournissant les options des paramètres de type 'liste' (fixes ou via requête SQL Oracle).
 */
class ListOptionsProvider
{
    protected SqlGuard $sqlGuard;

    public function __construct(?SqlGuard $sqlGuard = null)
    {
        $this->sqlGuard = $sqlGuard ?? new SqlGuard();
    }

    /**
     * Charge la liste des options pour un paramètre donné.
     *
     * @param array $param Définition du paramètre
     * @return array<int, array{valeur: string, libelle: string}> Liste d'options avec 'valeur' et 'libelle'
     */
    public function getOptions(array $param): array
    {
        if (($param['type'] ?? '') !== 'liste' || ! isset($param['source'])) {
            return [];
        }

        $source = $param['source'];
        $sourceType = $source['type'] ?? 'fixe';
        $options = [];

        if ($sourceType === 'fixe') {
            foreach ($source['options'] ?? [] as $opt) {
                $options[] = [
                    'valeur'  => (string) ($opt['valeur'] ?? ''),
                    'libelle' => (string) ($opt['libelle'] ?? ''),
                ];
            }
        } elseif ($sourceType === 'requete') {
            $sqlLines = $source['requete_sql'] ?? [];
            $sql = is_array($sqlLines) ? implode("\n", $sqlLines) : (string) $sqlLines;

            // Validation par SqlGuard
            $this->sqlGuard->check($sql);

            // Limitation à 500 options pour éviter de surcharger l'interface
            $limitedSql = "SELECT * FROM ({$sql}) WHERE ROWNUM <= 500";

            $rows = DB::connection('oracle')->select($limitedSql);

            if (count($rows) >= 500) {
                Log::warning(sprintf(
                    "Plafond de 500 options atteint pour le paramètre '%s' (liste tronquée).",
                    $param['nom'] ?? 'inconnu'
                ));
            }

            foreach ($rows as $row) {
                $rowArr = (array) $row;
                // Normaliser les clés en minuscules
                $lowerRow = array_change_key_case($rowArr, CASE_LOWER);
                $options[] = [
                    'valeur'  => (string) ($lowerRow['valeur'] ?? ''),
                    'libelle' => (string) ($lowerRow['libelle'] ?? ''),
                ];
            }
        }

        return $options;
    }
}
