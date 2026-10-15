<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\LogReader;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogController extends Controller
{
    public function __construct(
        protected LogReader $logReader,
        protected CatalogueRepository $catalogueRepository,
    ) {}

    /**
     * GET /admin/logs
     * Journal d'activité administrateur : Exécutions et Connexions.
     */
    public function index(Request $request): View
    {
        $onglet = $request->query('onglet', 'executions');
        if (! in_array($onglet, ['executions', 'connexions'], true)) {
            $onglet = 'executions';
        }

        $filters = [
            'recherche'   => $request->query('q', ''),
            'mode'        => $request->query('mode', 'tous'),
            'statut'      => $request->query('statut', 'tous'),
            'date_debut'  => $request->query('date_debut'),
            'date_fin'    => $request->query('date_fin'),
            'utilisateur' => $request->query('utilisateur'),
            'extraction'  => $request->query('extraction'),
        ];

        $page = (int) $request->query('page', 1);

        $kpis = $this->logReader->getAdminKpis();

        if ($onglet === 'connexions') {
            $logs = $this->logReader->getAuthLogs($filters, $page, perPage: 50);
        } else {
            $logs = $this->logReader->getExecutionLogs($filters, $page, perPage: 50);
        }

        return view('admin.logs.index', [
            'onglet'  => $onglet,
            'filters' => $filters,
            'kpis'    => $kpis,
            'logs'    => $logs,
        ]);
    }

    /**
     * GET /admin/logs/export
     * Export CSV du journal (neutralisation des formules selon AGENTS.md).
     */
    public function export(Request $request): StreamedResponse
    {
        $onglet = $request->query('onglet', 'executions');
        $filters = [
            'recherche'   => $request->query('q', ''),
            'mode'        => $request->query('mode', 'tous'),
            'statut'      => $request->query('statut', 'tous'),
            'date_debut'  => $request->query('date_debut'),
            'date_fin'    => $request->query('date_fin'),
            'utilisateur' => $request->query('utilisateur'),
            'extraction'  => $request->query('extraction'),
        ];

        $filename = ($onglet === 'connexions' ? 'journal_connexions_' : 'journal_activites_') . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($onglet, $filters) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");

            if ($onglet === 'connexions') {
                fputcsv($handle, ['ID', 'Date et heure', 'Identifiant', 'Événement', 'Statut', 'Adresse IP', 'Navigateur'], ';');
                // Récupération de l'ensemble filtré (page 1 avec grand plafond pour export)
                $paginator = $this->logReader->getAuthLogs($filters, 1, perPage: 10000);
                foreach ($paginator->items() as $row) {
                    fputcsv($handle, [
                        $this->sanitizeCsvField($row['id'] ?? ''),
                        $this->sanitizeCsvField($row['date'] ?? ''),
                        $this->sanitizeCsvField($row['identifiant'] ?? ''),
                        $this->sanitizeCsvField($row['event_label'] ?? $row['event'] ?? ''),
                        $this->sanitizeCsvField($row['statut'] ?? ''),
                        $this->sanitizeCsvField($row['ip'] ?? ''),
                        $this->sanitizeCsvField($row['user_agent'] ?? ''),
                    ], ';');
                }
            } else {
                fputcsv($handle, ['Identifiant', 'Date et heure', 'Utilisateur', 'Activité', 'Code', 'Extraction', 'Statut', 'Lignes', 'Tronqué', 'Durée (ms)', 'Paramètres'], ';');
                $paginator = $this->logReader->getExecutionLogs($filters, 1, perPage: 10000);
                foreach ($paginator->items() as $row) {
                    $paramsStr = json_encode($row['parametres'] ?? [], JSON_UNESCAPED_UNICODE);
                    fputcsv($handle, [
                        $this->sanitizeCsvField($row['id'] ?? ''),
                        $this->sanitizeCsvField($row['date'] ?? ''),
                        $this->sanitizeCsvField($row['utilisateur'] ?? ''),
                        $this->sanitizeCsvField($row['mode'] ?? ''),
                        $this->sanitizeCsvField($row['code'] ?? ''),
                        $this->sanitizeCsvField($row['titre'] ?? ''),
                        $this->sanitizeCsvField($row['statut'] ?? ''),
                        $row['nb_lignes'] ?? 0,
                        ($row['tronque'] ?? false) ? 'Oui' : 'Non',
                        $row['duree_ms'] ?? 0,
                        $this->sanitizeCsvField($paramsStr),
                    ], ';');
                }
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
