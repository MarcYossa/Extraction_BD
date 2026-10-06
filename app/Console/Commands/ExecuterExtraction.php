<?php

namespace App\Console\Commands;

use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\ColumnFormatter;
use App\Services\Extraction\ExecutionLogger;
use App\Services\Extraction\ExtractionRunner;
use App\Services\Extraction\ListOptionsProvider;
use App\Services\Extraction\ParameterResolver;
use App\Services\Extraction\QueryCompiler;
use Illuminate\Console\Command;
use Throwable;

class ExecuterExtraction extends Command
{
    /**
     * Le nom et la signature de la commande.
     *
     * @var string
     */
    protected $signature = 'extraction:executer
                            {code : Code de l\'extraction (ex: CPT_001)}
                            {--p=* : Paramètre sous la forme nom=valeur (ex: --p=agence=1)}
                            {--limit= : Plafond optionnel de lignes}';

    /**
     * La description de la commande.
     *
     * @var string
     */
    protected $description = 'Exécute une extraction du catalogue sur Oracle avec ses paramètres.';

    public function handle(
        CatalogueRepository $repository,
        ListOptionsProvider $listOptionsProvider,
        ParameterResolver $parameterResolver,
        QueryCompiler $queryCompiler,
        ExtractionRunner $runner,
        ColumnFormatter $formatter,
        ExecutionLogger $logger
    ): int {
        $code = strtoupper($this->argument('code'));
        $this->info("--- Exécution de l'extraction [{$code}] ---");

        $extraction = $repository->find($code);
        if (! $extraction) {
            $this->error("Extraction [{$code}] introuvable dans le catalogue.");
            return self::FAILURE;
        }

        // Récupération des options passées via --p=nom=valeur
        $rawParams = [];
        foreach ($this->option('p') as $p) {
            if (str_contains($p, '=')) {
                [$k, $v] = explode('=', $p, 2);
                $rawParams[strtolower(trim($k))] = trim($v);
            }
        }

        // Préparation des options autorisées pour les listes
        $declaredParams = $extraction['parametres'] ?? [];
        $allowedOptions = [];
        foreach ($declaredParams as $pDef) {
            if (($pDef['type'] ?? '') === 'liste') {
                $pName = strtolower($pDef['nom'] ?? '');
                $opts = $listOptionsProvider->getOptions($pDef);
                $allowedOptions[$pName] = array_column($opts, 'valeur');
            }
        }

        // Résolution des paramètres
        try {
            $resolvedParams = $parameterResolver->resolve($declaredParams, $rawParams, $allowedOptions);
        } catch (Throwable $e) {
            $this->error("Erreur de paramétrage : " . $e->getMessage());
            return self::FAILURE;
        }

        // Compilation de la requête
        $compiled = $queryCompiler->compile($extraction['requete_sql'] ?? [], $declaredParams, $resolvedParams);

        // Exécution de la requête sur Oracle
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        try {
            $result = $runner->run($compiled['sql'], $compiled['bindings'], $limit);
        } catch (Throwable $e) {
            $this->error("Erreur lors de l'exécution sur Oracle : " . $e->getMessage());
            $logger->logExecution('cli', $code, $extraction['version'] ?? 1, 'execution', $resolvedParams, 'echec', 0, false, 0);
            return self::FAILURE;
        }

        // Formater les lignes pour la console
        $declaredCols = $extraction['colonnes'] ?? [];
        $formattedRows = array_map(function ($row) use ($formatter, $declaredCols) {
            return $formatter->formatRow($row, $declaredCols, false);
        }, $result['rows']);

        // Journaliser le succès
        $logger->logExecution(
            'cli',
            $code,
            $extraction['version'] ?? 1,
            'execution',
            $resolvedParams,
            'succes',
            $result['count'],
            $result['truncated'],
            $result['duration_ms']
        );

        // Affichage des métadonnées
        $this->line(sprintf("Colonnes      : %s", implode(', ', $result['columns'])));
        $this->line(sprintf("Lignes        : %d", $result['count']));
        $this->line(sprintf("Tronqué       : %s", $result['truncated'] ? 'Oui (plafond atteint)' : 'Non'));
        $this->line(sprintf("Durée         : %d ms", $result['duration_ms']));

        if (! empty($formattedRows)) {
            $headers = array_keys($formattedRows[0]);
            $this->table($headers, array_slice($formattedRows, 0, 20));
            if (count($formattedRows) > 20) {
                $this->comment(sprintf("... et %d autres lignes (20 premières affichées).", count($formattedRows) - 20));
            }
        }

        return self::SUCCESS;
    }
}
