<?php

namespace App\Http\Controllers;

use App\Services\Extraction\ExecutionLogger;
use App\Services\Extraction\ExtractionRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Controleur TEMPORAIRE de validation du socle technique (etape 7).
 * Il sera remplace par le vrai module d'extraction (catalogue JSON, authentification, roles).
 */
class SocleTestController extends Controller
{
    private const EXTRACTION_ID = 'B01-comptes-par-agence';

    private const SQL = <<<'SQL'
SELECT a.nom_agence, c.num_compte, c.type_compte, c.devise, c.date_ouverture, c.statut
FROM amp_test.compte c
JOIN amp_test.agence a ON a.agence_id = c.agence_id
WHERE c.agence_id = ?
  AND c.date_ouverture BETWEEN TO_DATE(?, 'YYYY-MM-DD') AND TO_DATE(?, 'YYYY-MM-DD')
  AND c.statut = ?
ORDER BY c.date_ouverture, c.num_compte
SQL;

    public function __construct(
        private ExtractionRunner $runner,
        private ExecutionLogger $logger,
    ) {
    }

    public function index(Request $request)
    {
        $agences = DB::connection('oracle')->select(
            'SELECT agence_id, nom_agence FROM amp_test.agence ORDER BY nom_agence'
        );

        $errors = [];
        $result = null;
        $fail = null;
        $params = $request->query();

        if ($request->hasAny(['agence', 'date_debut', 'date_fin', 'statut'])) {
            $validator = $this->validator($request);
            if ($validator->fails()) {
                $errors = $validator->errors()->all();
            } else {
                [$result, $fail] = $this->execute($validator->validated(), 'affichage');
            }
        }

        return view('socle-test', [
            'agences' => $agences,
            'params' => $params + ['agence' => 1, 'date_debut' => '2018-01-01', 'date_fin' => '2023-12-31', 'statut' => 'OUVERT'],
            'errors' => $errors,
            'fail' => $fail,
            'result' => $result,
            'query' => $request->getQueryString(),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $validator = $this->validator($request);
        abort_if($validator->fails(), 422, 'Parametres invalides.');

        [$result, $fail] = $this->execute($validator->validated(), 'export_csv');
        abort_if($fail !== null, 500, $fail);

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 : accents corrects dans Excel
            fputcsv($out, $result['columns'], ';', '"', '\\');
            foreach ($result['rows'] as $row) {
                fputcsv($out, array_map([$this, 'csvSafe'], array_values($row)), ';', '"', '\\');
            }
            fclose($out);
        }, 'extraction_comptes_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function validator(Request $request)
    {
        return Validator::make($request->query(), [
            'agence' => ['required', 'integer', 'min:1'],
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'statut' => ['required', 'in:OUVERT,BLOQUE,CLOTURE'],
        ]);
    }

    /** @return array{0: array|null, 1: string|null} */
    private function execute(array $p, string $mode): array
    {
        $entry = [
            'utilisateur' => 'anonyme (test du socle)',
            'extraction' => self::EXTRACTION_ID,
            'mode' => $mode,
            'parametres' => $p,
        ];

        try {
            $result = $this->runner->run(self::SQL, [
                (int) $p['agence'], $p['date_debut'], $p['date_fin'], $p['statut'],
            ]);
            $this->logger->record($entry + [
                'statut' => 'SUCCES',
                'nb_lignes' => count($result['rows']),
                'tronque' => $result['truncated'],
                'duree_ms' => $result['duration_ms'],
            ]);

            return [$result, null];
        } catch (Throwable $e) {
            report($e); // detail technique dans storage/logs/laravel.log
            $this->logger->record($entry + ['statut' => 'ERREUR']);

            return [null, "L'extraction a echoue. Le detail est dans le journal technique."];
        }
    }

    /** Neutralise les formules Excel (injection CSV) : =, +, -, @ en debut de cellule. */
    private function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
