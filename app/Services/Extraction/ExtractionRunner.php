<?php

namespace App\Services\Extraction;

use Illuminate\Support\Facades\DB;

/**
 * Execute une requete de lecture parametree (marqueurs ?) avec plafond de resultats.
 */
class ExtractionRunner
{
    public function __construct(private SqlGuard $guard)
    {
    }

    /**
     * @param  array<int, mixed>  $bindings  valeurs liees, dans l'ordre des marqueurs ?
     * @return array{columns: array<int,string>, rows: array<int,array>, truncated: bool, duration_ms: int, max_rows: int}
     */
    public function run(string $sql, array $bindings = []): array
    {
        $this->guard->assertReadOnly($sql);

        $max = max(1, (int) config('extraction.max_rows'));
        $start = microtime(true);

        // On demande une ligne de plus que le plafond pour savoir si le resultat est tronque.
        $limited = 'SELECT * FROM (' . $sql . ') FETCH FIRST ' . ($max + 1) . ' ROWS ONLY';

        $rows = array_map(
            static fn ($row) => (array) $row,
            DB::connection('oracle')->select($limited, $bindings)
        );

        $truncated = count($rows) > $max;
        if ($truncated) {
            $rows = array_slice($rows, 0, $max);
        }

        return [
            'columns' => $rows ? array_keys($rows[0]) : [],
            'rows' => $rows,
            'truncated' => $truncated,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            'max_rows' => $max,
        ];
    }
}
