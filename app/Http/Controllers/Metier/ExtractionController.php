<?php

namespace App\Http\Controllers\Metier;

use App\Http\Controllers\Controller;
use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\ColumnFormatter;
use App\Services\Extraction\CsvExporter;
use App\Services\Extraction\ExecutionLogger;
use App\Services\Extraction\ExtractionRunner;
use App\Services\Extraction\ListOptionsProvider;
use App\Services\Extraction\ParameterResolver;
use App\Services\Extraction\QueryCompiler;
use App\Services\Extraction\ResultStore;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExtractionController extends Controller
{
    public function __construct(
        private readonly CatalogueRepository $catalogue,
    ) {}

    /**
     * GET /extractions/{code}
     * Écran M03 : Saisie des paramètres d'exécution d'une extraction active.
     */
    public function show(
        Request $request,
        string $code,
        ListOptionsProvider $optionsProvider,
        ResultStore $resultStore
    ): View {
        $extraction = $this->catalogue->find($code);

        if (! $extraction || ($extraction['statut'] ?? '') !== 'active') {
            abort(404);
        }

        $parametres = $extraction['parametres'] ?? [];
        $optionsByParam = [];

        foreach ($parametres as $param) {
            if (($param['type'] ?? '') === 'liste') {
                try {
                    $optionsByParam[$param['nom']] = $optionsProvider->getOptions($param);
                } catch (\Throwable $e) {
                    $optionsByParam[$param['nom']] = [];
                }
            }
        }

        // Pré-remplissage : session (old_inputs), ancien résultat par jeton, ou vide
        $values = session()->get('old_inputs', []);
        $jeton = $request->query('jeton');
        if (empty($values) && $jeton && is_string($jeton)) {
            $prevResult = $resultStore->get($jeton, auth()->user()->identifiant);
            if ($prevResult && isset($prevResult['parametres'])) {
                $values = $prevResult['parametres'];
            }
        }

        // Support du pré-remplissage direct pour l'action « Relancer »
        if (empty($values) && $request->has('params') && is_array($request->query('params'))) {
            $values = $request->query('params');
        }

        return view('metier.extractions.show', [
            'extraction'     => $extraction,
            'optionsByParam' => $optionsByParam,
            'values'         => $values,
        ]);
    }

    /**
     * POST /extractions/{code}/execute
     * Exécution d'une extraction active avec paramètres et conservation du résultat (D13).
     */
    public function execute(
        Request $request,
        string $code,
        ListOptionsProvider $optionsProvider,
        ParameterResolver $parameterResolver,
        QueryCompiler $queryCompiler,
        ExtractionRunner $extractionRunner,
        ResultStore $resultStore,
        ExecutionLogger $logger
    ): RedirectResponse {
        $extraction = $this->catalogue->find($code);

        if (! $extraction || ($extraction['statut'] ?? '') !== 'active') {
            abort(404);
        }

        $declaredParams = $extraction['parametres'] ?? [];

        // Récupérer les options autorisées pour les listes
        $allowedListOptions = [];
        $optionsByParam = [];
        foreach ($declaredParams as $param) {
            if (($param['type'] ?? '') === 'liste') {
                try {
                    $opts = $optionsProvider->getOptions($param);
                    $optionsByParam[$param['nom']] = $opts;
                    $allowedListOptions[$param['nom']] = array_column($opts, 'valeur');
                } catch (\Throwable $e) {
                    $optionsByParam[$param['nom']] = [];
                    $allowedListOptions[$param['nom']] = [];
                }
            }
        }

        // 1. Contrôle et validation des paramètres
        try {
            $resolvedParams = $parameterResolver->resolve($declaredParams, $request->all(), $allowedListOptions);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $user = auth()->user()->identifiant;
        $version = (int) ($extraction['version'] ?? 1);
        $sqlLines = $extraction['requete_sql'] ?? [];

        // 2. Compilation de la requête SQL
        $compiledSql = null;
        try {
            $compiled = $queryCompiler->compile($sqlLines, $declaredParams, $resolvedParams);
            $compiledSql = $compiled['sql'];
            $bindings = $compiled['bindings'];
        } catch (\Throwable $e) {
            Log::error("Erreur compilation requête extraction {$code}: " . $e->getMessage());
            return back()->with('error', "Erreur de compilation de la requête SQL.")->withInput();
        }

        // 3. Exécution avec limite de max_rows et timeout
        $start = microtime(true);
        $maxRows = (int) config('extraction.max_rows', 10000);
        $timeout = (int) config('extraction.timeout_seconds', 60);

        try {
            $runnerResult = $extractionRunner->run($compiledSql, $bindings, $maxRows, $timeout);

            $dureeMs = $runnerResult['duration_ms'];
            $isTruncated = $runnerResult['truncated'];
            $totalCount = $runnerResult['count']; // Conforme D21 : en exécution métier, le total = lignes reçues (jamais plus que le plafond)

            // Formatage des lignes pour l'affichage écran
            $formatter = new ColumnFormatter();
            $formattedRows = array_map(function ($row) use ($formatter, $extraction) {
                return $formatter->formatRow($row, $extraction['colonnes'] ?? [], false);
            }, $runnerResult['rows']);

            $parametresAffiches = $this->formatParametresAffiches($declaredParams, $resolvedParams, $optionsByParam);

            // 4. Enregistrement dans le ResultStore (15 minutes, D13)
            $jeton = $resultStore->store(
                utilisateur: $user,
                code: $code,
                version: $version,
                parametres: $resolvedParams,
                colonnes: $extraction['colonnes'] ?? [],
                lignes: $formattedRows,
                dureeMs: $dureeMs,
                tronque: $isTruncated,
                totalLignes: $totalCount,
                parametresAffiches: $parametresAffiches,
                rawLignes: $runnerResult['rows']
            );

            // 5. Journalisation (mode: execution)
            $logger->logExecution(
                utilisateur: $user,
                code: $code,
                version: $version,
                mode: 'execution',
                parametres: $resolvedParams,
                statut: 'succes',
                nbLignes: $totalCount,
                tronque: $isTruncated,
                dureeMs: $dureeMs
            );

            return redirect()->route('extractions.resultats', [
                'code'  => $code,
                'jeton' => $jeton,
            ]);

        } catch (\Throwable $e) {
            $dureeMs = (int) round((microtime(true) - $start) * 1000);
            Log::error("Échec de l'exécution métier de l'extraction {$code}: " . $e->getMessage(), [
                'exception' => $e,
                'sql'       => $compiledSql ?? null,
            ]);

            // Journalisation de l'échec
            $logger->logExecution(
                utilisateur: $user,
                code: $code,
                version: $version,
                mode: 'execution',
                parametres: $resolvedParams ?? [],
                statut: 'echec',
                nbLignes: 0,
                tronque: false,
                dureeMs: $dureeMs
            );

            $msg = $e->getMessage();
            $isTimeout = str_contains($msg, 'ORA-01013')
                || stripos($msg, 'timeout') !== false
                || stripos($msg, 'timed out') !== false
                || $dureeMs >= ($timeout * 1000);

            if ($isTimeout) {
                $errorText = sprintf("L'extraction a dépassé le délai de %d secondes. Affinez vos critères.", $timeout);
            } else {
                $errorText = "Une erreur est survenue lors de l'exécution de l'extraction sur la base de données.";
            }

            return back()->with('error', $errorText)->withInput();
        }
    }

    /**
     * GET /extractions/{code}/resultats/{jeton}
     * Écran M04 : Affichage du résultat de l'extraction métier et pagination (D8, D21).
     */
    public function resultats(
        string $code,
        string $jeton,
        ResultStore $resultStore
    ): View {
        $extraction = $this->catalogue->find($code);

        if (! $extraction || ($extraction['statut'] ?? '') !== 'active') {
            abort(404);
        }

        $resultat = $resultStore->get($jeton, auth()->user()->identifiant);
        if (! $resultat || ($resultat['code'] ?? '') !== $code) {
            return view('metier.extractions.expire', [
                'code'       => $code,
                'extraction' => $extraction,
            ]);
        }

        return view('metier.extractions.resultats', [
            'extraction' => $extraction,
            'resultat'   => $resultat,
            'jeton'      => $jeton,
        ]);
    }

    /**
     * GET /extractions/{code}/csv/{jeton}
     * Export CSV du résultat conservé dans ResultStore (sans requête Oracle supplémentaire, D13).
     */
    public function csv(
        string $code,
        string $jeton,
        ResultStore $resultStore,
        CsvExporter $exporter,
        ExecutionLogger $logger
    ): StreamedResponse|View {
        $extraction = $this->catalogue->find($code);

        if (! $extraction || ($extraction['statut'] ?? '') !== 'active') {
            abort(404);
        }

        $resultat = $resultStore->get($jeton, auth()->user()->identifiant);
        if (! $resultat || ($resultat['code'] ?? '') !== $code) {
            return view('metier.extractions.expire', [
                'code'       => $code,
                'extraction' => $extraction,
            ]);
        }

        $user = auth()->user()->identifiant;
        $version = (int) ($extraction['version'] ?? 1);
        $nbLignes = (int) ($resultat['total_lignes'] ?? count($resultat['lignes'] ?? []));
        $tronque = (bool) ($resultat['tronque'] ?? false);

        // Journalisation (mode: export_csv)
        $logger->logExecution(
            utilisateur: $user,
            code: $code,
            version: $version,
            mode: 'export_csv',
            parametres: $resultat['parametres'] ?? [],
            statut: 'succes',
            nbLignes: $nbLignes,
            tronque: $tronque,
            dureeMs: 0
        );

        $filename = sprintf('%s_%s.csv', $code, now()->format('Ymd_Hi'));

        return $exporter->download($resultat, $filename);
    }

    /**
     * Prépare les paramètres affichés dans le bandeau récapitulatif [ 'Libellé' => 'Valeur lisible' ].
     *
     * @param array<int, array> $declaredParams
     * @param array<string, mixed> $resolvedParams
     * @param array<string, array> $optionsByParam
     * @return array<string, string>
     */
    private function formatParametresAffiches(
        array $declaredParams,
        array $resolvedParams,
        array $optionsByParam
    ): array {
        $items = [];

        foreach ($declaredParams as $p) {
            $nom = $p['nom'] ?? '';
            $libelle = $p['libelle'] ?? $nom;
            $val = $resolvedParams[$nom] ?? null;

            if ($val === null || $val === '') {
                if (($p['type'] ?? '') === 'liste' && ! empty($p['option_toutes'])) {
                    $cleaned = trim($p['option_toutes'], "— \t\n\r\0\x0B");
                    $items[$libelle] = $cleaned ?: $p['option_toutes'];
                } else {
                    $items[$libelle] = '—';
                }
                continue;
            }

            switch ($p['type'] ?? 'texte') {
                case 'date':
                    try {
                        $items[$libelle] = Carbon::parse($val)->format('d/m/Y');
                    } catch (\Throwable $e) {
                        $items[$libelle] = (string) $val;
                    }
                    break;

                case 'booleen':
                    $items[$libelle] = $val ? 'Oui' : 'Non';
                    break;

                case 'liste':
                    $opts = $optionsByParam[$nom] ?? [];
                    $foundLabel = null;
                    foreach ($opts as $opt) {
                        if ((string) $opt['valeur'] === (string) $val) {
                            $foundLabel = $opt['libelle'];
                            break;
                        }
                    }
                    $items[$libelle] = $foundLabel ?? (string) $val;
                    break;

                default:
                    $items[$libelle] = (string) $val;
                    break;
            }
        }

        return $items;
    }
}
