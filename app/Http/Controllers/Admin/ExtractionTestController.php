<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\ColumnFormatter;
use App\Services\Extraction\ExecutionLogger;
use App\Services\Extraction\ExtractionRunner;
use App\Services\Extraction\ListOptionsProvider;
use App\Services\Extraction\ParameterResolver;
use App\Services\Extraction\QueryCompiler;
use App\Services\Extraction\ResultStore;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExtractionTestController extends Controller
{
    /**
     * GET /admin/extractions/{code}/test
     * Écran S06 : Saisie des paramètres d'exécution avant publication.
     */
    public function testForm(
        Request $request,
        string $code,
        CatalogueRepository $repository,
        ListOptionsProvider $optionsProvider,
        ResultStore $resultStore
    ): View {
        $extraction = $repository->find($code);
        if (! $extraction) {
            abort(404, "Extraction introuvable : {$code}");
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

        // Pré-remplissage des valeurs : session, ancien résultat par jeton, ou vide
        $values = session()->get('old_inputs', []);
        $jeton = $request->query('jeton');
        if (empty($values) && $jeton && is_string($jeton)) {
            $prevResult = $resultStore->get($jeton, auth()->user()->identifiant);
            if ($prevResult && isset($prevResult['parametres'])) {
                $values = $prevResult['parametres'];
            }
        }

        return view('admin.extractions.test', [
            'extraction'     => $extraction,
            'optionsByParam' => $optionsByParam,
            'values'         => $values,
        ]);
    }

    /**
     * POST /admin/extractions/{code}/test
     * Exécution du test (limite 100 lignes, calcul COUNT(*) exact, ResultStore, journal).
     */
    public function executeTest(
        Request $request,
        string $code,
        CatalogueRepository $repository,
        ListOptionsProvider $optionsProvider,
        ParameterResolver $parameterResolver,
        QueryCompiler $queryCompiler,
        ExtractionRunner $extractionRunner,
        ResultStore $resultStore,
        ExecutionLogger $logger
    ): RedirectResponse {
        $extraction = $repository->find($code);
        if (! $extraction) {
            abort(404, "Extraction introuvable : {$code}");
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

        // 1. Contrôle et résolution des paramètres
        try {
            $resolvedParams = $parameterResolver->resolve($declaredParams, $request->all(), $allowedListOptions);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $user = auth()->user()->identifiant;
        $version = (int) ($extraction['version'] ?? 1);
        $sqlLines = $extraction['requete_sql'] ?? [];
        $sqlTemplate = is_array($sqlLines) ? implode("\n", $sqlLines) : (string) $sqlLines;

        // 2. Compilation de la requête SQL
        try {
            [$compiledSql, $bindings] = $queryCompiler->compile($sqlTemplate, $resolvedParams);
        } catch (\Throwable $e) {
            Log::error("Erreur compilation requête test extraction {$code}: " . $e->getMessage());
            return back()->with('error', "Erreur de compilation de la requête SQL.")->withInput();
        }

        // 3. Exécution avec limite de 100 lignes
        $start = microtime(true);
        try {
            $runnerResult = $extractionRunner->run($compiledSql, $bindings, 100);

            // 4. Calcul du total exact par un COUNT(*) supplémentaire (D21) si tronqué
            $totalCount = $runnerResult['count'];
            if ($runnerResult['truncated']) {
                try {
                    $countSql = sprintf("SELECT COUNT(*) AS total_count FROM (%s)", $compiledSql);
                    $countRows = DB::connection('oracle')->select($countSql, $bindings);
                    $firstRow = array_change_key_case((array) ($countRows[0] ?? []), CASE_LOWER);
                    if (isset($firstRow['total_count'])) {
                        $totalCount = (int) $firstRow['total_count'];
                    }
                } catch (\Throwable $e) {
                    Log::warning("Impossible d'exécuter COUNT(*) pour {$code}: " . $e->getMessage());
                }
            }

            $dureeMs = $runnerResult['duration_ms'];

            // Préparation des paramètres lisibles pour le bandeau
            $parametresAffiches = $this->formatParametresAffiches($declaredParams, $resolvedParams, $optionsByParam);

            // Formatage des lignes via ColumnFormatter
            $formatter = new ColumnFormatter();
            $formattedRows = array_map(function ($row) use ($formatter, $extraction) {
                return $formatter->formatRow($row, $extraction['colonnes'] ?? [], false);
            }, $runnerResult['rows']);

            // Enregistrement dans le ResultStore (15 minutes)
            $jeton = $resultStore->store(
                utilisateur: $user,
                code: $code,
                version: $version,
                parametres: $resolvedParams,
                colonnes: $extraction['colonnes'] ?? [],
                lignes: $formattedRows,
                dureeMs: $dureeMs,
                tronque: $runnerResult['truncated'],
                totalLignes: $totalCount,
                parametresAffiches: $parametresAffiches
            );

            // Enregistrement du dernier_test réussi dans queries.json
            $extraction['dernier_test'] = [
                'version'   => $version,
                'date'      => now()->toIso8601String(),
                'par'       => $user,
                'resultat'  => 'reussi',
                'nb_lignes' => $totalCount,
                'duree_ms'  => $dureeMs,
            ];
            $repository->save($extraction, $user);

            // Journalisation (mode: test)
            $logger->logExecution(
                utilisateur: $user,
                code: $code,
                version: $version,
                mode: 'test',
                parametres: $resolvedParams,
                statut: 'succes',
                nbLignes: $totalCount,
                tronque: $runnerResult['truncated'],
                dureeMs: $dureeMs
            );

            return redirect()->route('admin.extractions.test.resultat', [
                'code'  => $code,
                'jeton' => $jeton,
            ]);

        } catch (\Throwable $e) {
            $dureeMs = (int) round((microtime(true) - $start) * 1000);
            Log::error("Échec du test de l'extraction {$code}: " . $e->getMessage(), [
                'exception' => $e,
                'sql'       => $compiledSql ?? null,
            ]);

            // Enregistrement de dernier_test en échec
            $extraction['dernier_test'] = [
                'version'   => $version,
                'date'      => now()->toIso8601String(),
                'par'       => $user,
                'resultat'  => 'echec',
                'nb_lignes' => 0,
                'duree_ms'  => $dureeMs,
            ];
            $repository->save($extraction, $user);

            // Journalisation de l'échec
            $logger->logExecution(
                utilisateur: $user,
                code: $code,
                version: $version,
                mode: 'test',
                parametres: $resolvedParams ?? [],
                statut: 'echec',
                nbLignes: 0,
                tronque: false,
                dureeMs: $dureeMs
            );

            return back()->with('error', "Une erreur est survenue lors de l'exécution du test sur la base de données.")->withInput();
        }
    }

    /**
     * GET /admin/extractions/{code}/test/resultat/{jeton}
     * Écran S07 : Affichage du résultat de test et bouton de publication (D22).
     */
    public function resultat(
        string $code,
        string $jeton,
        CatalogueRepository $repository,
        ResultStore $resultStore
    ): View {
        $extraction = $repository->find($code);
        if (! $extraction) {
            abort(404, "Extraction introuvable : {$code}");
        }

        $resultat = $resultStore->get($jeton, auth()->user()->identifiant);
        if (! $resultat || ($resultat['code'] ?? '') !== $code) {
            abort(404, "Résultat de test introuvable ou expiré.");
        }

        // Règle D22 : Le bouton Publier n'apparaît que si le dernier test a réussi pour la version courante
        $dernierTest = $extraction['dernier_test'] ?? null;
        $peutPublier = $dernierTest
            && ($dernierTest['resultat'] ?? '') === 'reussi'
            && ($dernierTest['version'] ?? 0) === (int) ($extraction['version'] ?? 1);

        return view('admin.extractions.resultat', [
            'extraction'  => $extraction,
            'resultat'    => $resultat,
            'jeton'       => $jeton,
            'peutPublier' => $peutPublier,
        ]);
    }

    /**
     * Formate les valeurs des paramètres en chaînes lisibles pour le bandeau récapitulatif.
     */
    protected function formatParametresAffiches(array $declaredParams, array $resolvedParams, array $optionsByParam): array
    {
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
