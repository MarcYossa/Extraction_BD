<?php

namespace App\Services\Extraction;

/**
 * Service de compilation des requêtes SQL :
 * Convertit les paramètres nommés (:nom) en variables liées positionnelles (?)
 * et formate les dates avec TO_DATE(?, 'YYYY-MM-DD').
 */
class QueryCompiler
{
    /**
     * Compile une requête SQL et ses paramètres résolus.
     *
     * @param array|string $sqlLines Lignes de la requête SQL du catalogue
     * @param array<int, array> $declaredParams Déclaration des paramètres de l'extraction
     * @param array<string, mixed> $resolvedValues Valeurs résolues des paramètres (clé => valeur)
     * @return array{sql: string, bindings: array<int, mixed>} Requête compilée et liste ordonnée des binds
     */
    public function compile(array|string $sqlLines, array $declaredParams, array $resolvedValues): array
    {
        $rawSql = is_array($sqlLines) ? implode("\n", $sqlLines) : (string) $sqlLines;

        // Indexer les déclarations de paramètres par nom minuscules
        $paramDefs = [];
        foreach ($declaredParams as $p) {
            if (isset($p['nom'])) {
                $paramDefs[strtolower($p['nom'])] = $p;
            }
        }

        $bindings = [];

        // Découpage du SQL en segments hors apostrophes et segments entre apostrophes
        // Le motif '(?:[^']|'')*' gère correctement les apostrophes échappées (ex: 'd''épargne')
        $parts = preg_split("/('(?:[^']|'')*')/", $rawSql, -1, PREG_SPLIT_DELIM_CAPTURE);

        $compiledSql = '';

        foreach ($parts as $part) {
            // Si la partie commence et finit par une apostrophe, c't une chaîne littérale : la laisser intacte
            if (strlen($part) >= 2 && $part[0] === "'" && substr($part, -1) === "'") {
                $compiledSql .= $part;
                continue;
            }

            // Remplacement des :nom dans la partie hors apostrophes
            $partCompiled = preg_replace_callback('/:([a-z][a-z0-9_]{0,29})/i', function ($matches) use (&$bindings, $paramDefs, $resolvedValues) {
                $paramName = strtolower($matches[1]);
                $paramDef  = $paramDefs[$paramName] ?? null;

                $val = array_key_exists($paramName, $resolvedValues) ? $resolvedValues[$paramName] : null;
                $bindings[] = $val;

                // Si le paramètre est de type date, remplacer par TO_DATE(?, 'YYYY-MM-DD')
                if ($paramDef && ($paramDef['type'] ?? '') === 'date') {
                    return "TO_DATE(?, 'YYYY-MM-DD')";
                }

                return '?';
            }, $part);

            $compiledSql .= $partCompiled;
        }

        return [
            'sql'      => $compiledSql,
            'bindings' => $bindings,
        ];
    }
}
