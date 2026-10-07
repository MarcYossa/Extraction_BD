<?php

namespace App\Services\Extraction;

/**
 * Analyseur statique de colonnes SQL SELECT.
 * Extrait la liste des clés/alias de colonnes figurant dans la clause SELECT principale.
 */
class SelectColumnParser
{
    /**
     * Analyse une requête SQL (chaîne ou tableau de lignes) et retourne la liste des alias/clés de colonnes.
     *
     * @param string|array<int, string> $sql
     * @return array<int, string> Liste des clés de colonnes en minuscules
     */
    public static function parseColumns(string|array $sql): array
    {
        if (is_array($sql)) {
            $sql = implode("\n", $sql);
        }

        $sql = trim($sql);

        if ($sql === '') {
            return [];
        }

        // Supprimer la clause WITH au besoin pour trouver le SELECT principal
        $selectBody = self::extractSelectClause($sql);
        if (! $selectBody) {
            return [];
        }

        // Découper les expressions de colonnes par les virgules de premier niveau (hors parenthèses et guillemets)
        $columnExpressions = self::splitTopLevelCommas($selectBody);

        $columns = [];
        foreach ($columnExpressions as $expr) {
            $expr = trim($expr);
            if ($expr === '') {
                continue;
            }

            $alias = self::extractAliasFromExpression($expr);
            if ($alias !== '') {
                $columns[] = strtolower($alias);
            }
        }

        return $columns;
    }

    /**
     * Extrait la partie située entre SELECT et le FROM principal (ou la fin de la requête si pas de FROM).
     */
    protected static function extractSelectClause(string $sql): ?string
    {
        // Nettoyer les commentaires et sauts de ligne superflus
        $cleanSql = preg_replace('/--.*$/m', '', $sql);
        $cleanSql = preg_replace('/\/\*.*?\*\//s', '', $cleanSql);

        // Si la requête commence par WITH, chercher le dernier SELECT principal
        // Trouver l'indice de SELECT au niveau le plus haut
        $length = strlen($cleanSql);
        $selectPos = -1;
        $inString = false;
        $stringChar = '';
        $depth = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $cleanSql[$i];

            if ($inString) {
                if ($char === $stringChar) {
                    if ($i + 1 < $length && $cleanSql[$i + 1] === $stringChar) {
                        $i++; // Échappement ''
                    } else {
                        $inString = false;
                    }
                }
                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = true;
                $stringChar = $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth < 0) {
                    $depth = 0;
                }
                continue;
            }

            if ($depth === 0) {
                $sub = substr($cleanSql, $i);
                if (preg_match('/^SELECT\b/i', $sub)) {
                    $selectPos = $i;
                    break;
                }
            }
        }

        if ($selectPos === -1) {
            return null;
        }

        $afterSelect = substr($cleanSql, $selectPos + 6); // Après 'SELECT'

        // Chercher le 'FROM' correspondant au niveau 0 de parenthèses
        $fromPos = -1;
        $inString = false;
        $stringChar = '';
        $depth = 0;
        $afterLen = strlen($afterSelect);

        for ($i = 0; $i < $afterLen; $i++) {
            $char = $afterSelect[$i];

            if ($inString) {
                if ($char === $stringChar) {
                    if ($i + 1 < $afterLen && $afterSelect[$i + 1] === $stringChar) {
                        $i++;
                    } else {
                        $inString = false;
                    }
                }
                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = true;
                $stringChar = $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                continue;
            }

            if ($depth === 0) {
                $sub = substr($afterSelect, $i);
                if (preg_match('/^FROM\b/i', $sub)) {
                    $fromPos = $i;
                    break;
                }
            }
        }

        if ($fromPos !== -1) {
            return substr($afterSelect, 0, $fromPos);
        }

        return $afterSelect;
    }

    /**
     * Découpe la liste des colonnes par virgule au niveau 0 de parenthèses.
     *
     * @return array<int, string>
     */
    protected static function splitTopLevelCommas(string $str): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $stringChar = '';
        $len = strlen($str);

        for ($i = 0; $i < $len; $i++) {
            $char = $str[$i];

            if ($inString) {
                $current .= $char;
                if ($char === $stringChar) {
                    if ($i + 1 < $len && $str[$i + 1] === $stringChar) {
                        $current .= $str[$i + 1];
                        $i++;
                    } else {
                        $inString = false;
                    }
                }
                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = true;
                $stringChar = $char;
                $current .= $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $current .= $char;
                continue;
            }

            if ($char === ')') {
                $depth--;
                $current .= $char;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * Extrait le nom/alias d'une expression de colonne.
     * Exemples :
     * - "c.num_compte AS num_compte" -> "num_compte"
     * - "c.num_compte num_compte" -> "num_compte"
     * - "TRIM(c.prenom || ' ' || c.nom) AS nom_raison_sociale" -> "nom_raison_sociale"
     * - "a.nom_agence" -> "nom_agence"
     */
    protected static function extractAliasFromExpression(string $expr): string
    {
        $expr = trim($expr);

        // 1. Détecter un "AS alias" explicite à la fin (hors parenthèses/chaînes)
        if (preg_match('/\bAS\s+([a-zA-Z0-9_]+)$/i', $expr, $matches)) {
            return $matches[1];
        }

        // 2. Détecter un alias implicite à la fin (mot simple après espace, si ce n'est pas un mot-clé)
        if (preg_match('/\s+([a-zA-Z0-9_]+)$/i', $expr, $matches)) {
            $candidate = $matches[1];
            $keywords = ['END', 'ASC', 'DESC', 'NULL', 'TRUE', 'FALSE', 'THEN', 'ELSE', 'WHEN', 'AS'];
            if (! in_array(strtoupper($candidate), $keywords, true)) {
                return $candidate;
            }
        }

        // 3. Détecter "table.colonne" ou simple "colonne"
        if (preg_match('/(?:[a-zA-Z0-9_]+\.)?([a-zA-Z0-9_]+)$/', $expr, $matches)) {
            return $matches[1];
        }

        return $expr;
    }
}
