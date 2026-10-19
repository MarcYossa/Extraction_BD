<?php

namespace App\Http\Controllers\Metier;

use App\Http\Controllers\Controller;
use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\LogReader;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MesExtractionsController extends Controller
{
    public function __construct(
        protected LogReader $logReader,
        protected CatalogueRepository $catalogueRepository,
    ) {}

    /**
     * GET /mes-extractions
     * Historique personnel des activités de l'utilisateur connecté (D14, D16).
     */
    public function index(Request $request): View
    {
        $user = auth()->user()->identifiant;

        // Isolation stricte côté serveur : le filtre utilisateur est fixé à l'utilisateur authentifié
        $filters = [
            'utilisateur' => $user,
            'recherche'   => $request->query('q', ''),
            'mode'        => $request->query('mode', 'tous'),
            'statut'      => $request->query('statut', 'tous'),
            'date_debut'  => $request->query('date_debut'),
            'date_fin'    => $request->query('date_fin'),
        ];

        $page = (int) $request->query('page', 1);

        $kpis = $this->logReader->getUserKpis($user);
        $logs = $this->logReader->getExecutionLogs($filters, $page, perPage: 50);

        return view('metier.mes-extractions', [
            'filters' => $filters,
            'kpis'    => $kpis,
            'logs'    => $logs,
        ]);
    }

    /**
     * GET /mes-extractions/export
     * Exporte l'historique personnel de l'utilisateur connecté au format CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = auth()->user()->identifiant;

        // Isolation absolue : seules ses propres activités sont exportées
        $filters = [
            'utilisateur' => $user,
            'recherche'   => $request->query('q', ''),
            'mode'        => $request->query('mode', 'tous'),
            'statut'      => $request->query('statut', 'tous'),
            'date_debut'  => $request->query('date_debut'),
            'date_fin'    => $request->query('date_fin'),
        ];

        $filename = 'mon_historique_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM UTF-8

            fputcsv($handle, ['Identifiant', 'Date et heure', 'Activité', 'Code', 'Extraction', 'Statut', 'Lignes', 'Durée (ms)', 'Paramètres'], ';');

            $paginator = $this->logReader->getExecutionLogs($filters, 1, perPage: 10000);
            foreach ($paginator->items() as $row) {
                $paramsStr = json_encode($row['parametres'] ?? [], JSON_UNESCAPED_UNICODE);
                fputcsv($handle, [
                    $this->sanitizeCsvField($row['id'] ?? ''),
                    $this->sanitizeCsvField($row['date'] ?? ''),
                    $this->sanitizeCsvField($row['mode'] ?? ''),
                    $this->sanitizeCsvField($row['code'] ?? ''),
                    $this->sanitizeCsvField($row['titre'] ?? ''),
                    $this->sanitizeCsvField($row['statut'] ?? ''),
                    $row['nb_lignes'] ?? 0,
                    $row['duree_ms'] ?? 0,
                    $this->sanitizeCsvField($paramsStr),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Neutralise les risques d'injection CSV (règle AGENTS.md).
     */
    protected function sanitizeCsvField(mixed $value): string
    {
        $str = (string) $value;
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }
        return $str;
    }
}
