<?php

namespace App\Services\Extraction;

use Opis\JsonSchema\Validator;
use Throwable;

/**
 * Validateur du catalogue d'extractions et des règles métier croisées (Section 9 de la spécification).
 */
class CatalogueValidator
{
    protected SqlGuard $sqlGuard;
    protected string $schemaPath;

    public function __construct(?SqlGuard $sqlGuard = null)
    {
        $this->sqlGuard = $sqlGuard ?? new SqlGuard();
        $this->schemaPath = base_path('docs/design/catalogue/queries.schema.json');
    }

    /**
     * Valide un catalogue complet (contenu brut d'un queries.json).
     *
     * @return array<int, string> Liste des erreurs trouvées (vide si valide)
     */
    public function validateCatalogue(array $data): array
    {
        $errors = [];

        // 1. Validation du schéma JSON avec Opis
        if (file_exists($this->schemaPath)) {
            try {
                $validator = new Validator();
                $schemaData = json_decode(file_get_contents($this->schemaPath));
                if (is_object($schemaData) && isset($schemaData->{'$id'})) {
                    unset($schemaData->{'$id'});
                }

                $dataJson = json_decode(json_encode($data));
                $result = $validator->validate($dataJson, $schemaData);

                if (! $result->isValid()) {
                    $error = $result->error();
                    $formatter = new \Opis\JsonSchema\Errors\ErrorFormatter();
                    $formattedErrors = $formatter->format($error, false);

                    foreach ($formattedErrors as $path => $msg) {
                        $pathStr = is_array($path) ? implode('.', $path) : (string) $path;
                        $errors[] = sprintf("Schéma JSON invalide au chemin [%s] : %s", $pathStr, is_array($msg) ? implode(', ', $msg) : $msg);
                    }
                }
            } catch (Throwable $e) {
                $errors[] = "Erreur lors de la validation du schéma JSON : " . $e->getMessage();
            }
        }

        // 2. Unicité des codes dans le catalogue
        $extractions = $data['extractions'] ?? [];
        $codes = [];
        foreach ($extractions as $idx => $extraction) {
            $code = $extraction['code'] ?? null;
            if ($code) {
                if (in_array(strtoupper($code), $codes, true)) {
                    $errors[] = sprintf("Règle 2 : Le code [%s] est présent plusieurs fois dans le catalogue.", $code);
                } else {
                    $codes[] = strtoupper($code);
                }
            }

            // Valider chaque extraction individuellement pour les règles croisées 3 à 10
            $singleErrors = $this->validateExtractionRules($extraction, $extractions);
            foreach ($singleErrors as $err) {
                $errors[] = sprintf("Extraction [%s] : %s", $code ?? "#{$idx}", $err);
            }
        }

        return $errors;
    }

    /**
     * Valide une extraction individuelle et retourne la liste des erreurs.
     *
     * @return array<int, string>
     */
    public function validateExtraction(array $extraction, array $existingCatalogue = []): array
    {
        return $this->validateExtractionRules($extraction, $existingCatalogue);
    }

    /**
     * Valide les règles métier croisées (règles 3 à 10 de la spécification).
     */
    protected function validateExtractionRules(array $extraction, array $allExtractions = []): array
    {
        $errors = [];
        $code = $extraction['code'] ?? 'SANS_CODE';

        // Assemblage du SQL
        $sqlLines = $extraction['requete_sql'] ?? [];
        $rawSql = is_array($sqlLines) ? implode("\n", $sqlLines) : (string) $sqlLines;

        // Règle 4 : Contrôle par SqlGuard
        try {
            $this->sqlGuard->check($rawSql);
        } catch (Throwable $e) {
            $errors[] = "Règle 4 (SqlGuard) : Requête SQL invalide — " . $e->getMessage();
        }

        // Extraction des paramètres :nom de la requête SQL (hors textes entre apostrophes)
        $sqlCleaned = preg_replace("/'[^']*'/", "''", $rawSql);
        preg_match_all('/:([a-z][a-z0-9_]{0,29})/i', $sqlCleaned, $matches);
        $sqlParamNames = array_unique(array_map('strtolower', $matches[1] ?? []));

        // Règle 3 : Correspondance entre :nom dans le SQL et les parametres déclarés
        $declaredParams = $extraction['parametres'] ?? [];
        $declaredParamNames = [];
        foreach ($declaredParams as $pIdx => $param) {
            $pName = strtolower($param['nom'] ?? '');
            if (empty($pName)) {
                $errors[] = "Règle 3 : Le paramètre #{$pIdx} n'a pas de champ 'nom'.";
                continue;
            }

            if (in_array($pName, $declaredParamNames, true)) {
                $errors[] = sprintf("Règle 3 : Le paramètre [%s] est déclaré plusieurs fois.", $pName);
            } else {
                $declaredParamNames[] = $pName;
            }

            // Règle 7 : Source de type 'requete' pour un paramètre de type 'liste'
            if (($param['type'] ?? '') === 'liste' && isset($param['source']) && ($param['source']['type'] ?? '') === 'requete') {
                $sourceSqlLines = $param['source']['requete_sql'] ?? [];
                $sourceSql = is_array($sourceSqlLines) ? implode("\n", $sourceSqlLines) : (string) $sourceSqlLines;
                try {
                    $this->sqlGuard->check($sourceSql);
                } catch (Throwable $e) {
                    $errors[] = sprintf("Règle 7 : La requête de la liste [%s] est refusée par SqlGuard : %s", $pName, $e->getMessage());
                }

                if (! preg_match('/\bvaleur\b/i', $sourceSql) || ! preg_match('/\blibelle\b/i', $sourceSql)) {
                    $errors[] = sprintf("Règle 7 : La requête de la liste [%s] doit renvoyer les colonnes 'valeur' et 'libelle'.", $pName);
                }
            }

            // Règle 8 : apres_ou_egal désigne un paramètre date existant
            if (! empty($param['apres_ou_egal'])) {
                $targetDateName = strtolower($param['apres_ou_egal']);
                $foundTarget = false;
                foreach ($declaredParams as $otherP) {
                    if (strtolower($otherP['nom'] ?? '') === $targetDateName && ($otherP['type'] ?? '') === 'date') {
                        $foundTarget = true;
                        break;
                    }
                }
                if (! $foundTarget) {
                    $errors[] = sprintf("Règle 8 : Le paramètre [%s] fait référence à apres_ou_egal='%s' qui n'est pas un paramètre date valide.", $pName, $param['apres_ou_egal']);
                }
            }
        }

        // Vérification des deux sens pour la règle 3
        foreach ($sqlParamNames as $sqlP) {
            if (! in_array($sqlP, $declaredParamNames, true)) {
                $errors[] = sprintf("Règle 3 : Le paramètre :%s utilisé dans le SQL n'est pas déclaré dans 'parametres'.", $sqlP);
            }
        }
        foreach ($declaredParamNames as $declP) {
            if (! in_array($declP, $sqlParamNames, true)) {
                $errors[] = sprintf("Règle 3 : Le paramètre [%s] est déclaré mais n'est pas utilisé dans le SQL (:nom).", $declP);
            }
        }

        // Règle 5 : Colonnes SELECT uniques (alias AS)
        // Analyse sommaire des alias de colonnes après SELECT et avant FROM
        if (preg_match('/SELECT\s+(.+?)\s+FROM\b/is', $rawSql, $selectMatches)) {
            $selectClause = $selectMatches[1];
            // Séparer les colonnes (attention aux virgules dans les fonctions)
            $cols = preg_split('/,(?![^\(]*\))/', $selectClause);
            $aliases = [];
            foreach ($cols as $colExpr) {
                $colExpr = trim($colExpr);
                if (preg_match('/\s+AS\s+([a-z0-9_"]+)/i', $colExpr, $aliasMatch)) {
                    $alias = strtolower(trim($aliasMatch[1], '" '));
                } else {
                    $parts = preg_split('/\s+/', $colExpr);
                    $alias = strtolower(trim(end($parts), '" '));
                }

                if (! empty($alias) && $alias !== 'distinct') {
                    if (in_array($alias, $aliases, true)) {
                        $errors[] = sprintf("Règle 5 : La colonne [%s] est présente plusieurs fois dans la clause SELECT.", $alias);
                    } else {
                        $aliases[] = $alias;
                    }
                }
            }

            // Règle 6 : Les colonnes déclarées correspondent au SELECT
            $declaredCols = $extraction['colonnes'] ?? [];
            if (! empty($declaredCols) && ! empty($aliases)) {
                $declaredKeys = array_map(fn ($c) => strtolower($c['cle'] ?? ''), $declaredCols);
                if ($declaredKeys !== $aliases) {
                    $errors[] = sprintf(
                        "Règle 6 : Les colonnes déclarées (%s) ne correspondent pas aux alias de la clause SELECT (%s).",
                        implode(', ', $declaredKeys),
                        implode(', ', $aliases)
                    );
                }
            }
        }

        // Règle 9 : Passage au statut 'active' exige un dernier_test réussi de la même version
        if (($extraction['statut'] ?? '') === 'active') {
            $dernierTest = $extraction['dernier_test'] ?? null;
            if (! is_array($dernierTest) || ($dernierTest['resultat'] ?? '') !== 'reussi') {
                $errors[] = "Règle 9 : Impossible de passer au statut 'active' car le dernier test n'a pas été exécuté avec succès.";
            } elseif (($dernierTest['version'] ?? null) !== ($extraction['version'] ?? null)) {
                $errors[] = sprintf(
                    "Règle 9 : Le dernier test réussi porte sur la version %s, mais la version courante est %s.",
                    $dernierTest['version'] ?? 'null',
                    $extraction['version'] ?? 'null'
                );
            }
        }

        return $errors;
    }
}
