<?php

namespace App\Services\Extraction;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Exécute une requête SQL compilée sur la base Oracle (connexion 'oracle' en lecture seule)
 * avec limite de lignes (max_rows ou test) et gestion du délai maximal (timeout_seconds).
 */
class ExtractionRunner
{
    protected SqlGuard $guard;

    public function __construct(?SqlGuard $guard = null)
    {
        $this->guard = $guard ?? new SqlGuard();
    }

    /**
     * Exécute une requête SQL avec variables liées et limite de résultats.
     *
     * @param string $sql Requête SQL compilée (avec marqueurs ?)
     * @param array $bindings Valeurs liées dans l'ordre
     * @param int|null $maxRows Plafond de lignes (null = config extraction.max_rows)
     * @param int|null $timeoutSeconds Délai d'exécution max en secondes (null = config extraction.timeout_seconds)
     * @return array{columns: array<int, string>, rows: array<int, array>, count: int, truncated: bool, duration_ms: int, max_rows: int}
     */
    public function run(string $sql, array $bindings = [], ?int $maxRows = null, ?int $timeoutSeconds = null): array
    {
        $this->guard->assertReadOnly($sql);

        $limit = $maxRows ?? (int) config('extraction.max_rows', 10000);
        $limit = max(1, $limit);

        $timeout = $timeoutSeconds ?? (int) config('extraction.timeout_seconds', 60);

        // Application du délai maximal OCI8 si l'extension le supporte
        try {
            $connection = DB::connection('oracle');
            $pdo = $connection->getPdo();
            if ($pdo && function_exists('oci_set_call_timeout') && is_resource($pdo)) {
                @oci_set_call_timeout($pdo, $timeout * 1000);
            }
        } catch (Throwable $e) {
            // Ignorer si la sous-connexion n'est pas un ressource OCI8 directe (ex: SQLite en test)
        }

        $start = microtime(true);

        // Demande limit + 1 lignes pour détecter la tronquature
        $limitedSql = sprintf("SELECT * FROM (%s) WHERE ROWNUM <= %d", $sql, $limit + 1);

        $rawRows = DB::connection('oracle')->select($limitedSql, $bindings);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $rows = array_map(function ($row) {
            $arr = (array) $row;
            return array_change_key_case($arr, CASE_LOWER);
        }, $rawRows);

        $truncated = count($rows) > $limit;
        if ($truncated) {
            $rows = array_slice($rows, 0, $limit);
        }

        $columns = [];
        if (! empty($rows)) {
            $columns = array_keys($rows[0]);
        }

        return [
            'columns'     => $columns,
            'rows'        => $rows,
            'count'       => count($rows),
            'truncated'   => $truncated,
            'duration_ms' => $durationMs,
            'max_rows'    => $limit,
        ];
    }
}
