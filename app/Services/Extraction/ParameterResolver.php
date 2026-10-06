<?php

namespace App\Services\Extraction;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Service de contrôle, de validation et de conversion des paramètres d'extraction.
 */
class ParameterResolver
{
    /**
     * Valide et résout les valeurs saisies pour la liste des paramètres déclarés.
     *
     * @param array<int, array> $declaredParams Paramètres déclarés dans le catalogue
     * @param array<string, mixed> $inputs Saisies reçues de l'utilisateur
     * @param array<string, array> $allowedListOptions Options de liste autorisées par nom de paramètre
     * @return array<string, mixed> Valeurs résolues et typées pour l'exécution
     * @throws ValidationException En cas d'erreur de validation des saisies
     */
    public function resolve(array $declaredParams, array $inputs, array $allowedListOptions = []): array
    {
        $resolved = [];
        $errors = [];

        foreach ($declaredParams as $param) {
            $name        = $param['nom'] ?? null;
            $type        = $param['type'] ?? 'texte';
            $required    = (bool) ($param['obligatoire'] ?? false);
            $default     = $param['defaut'] ?? null;
            $optionToutes = $param['option_toutes'] ?? null;

            if (! $name) {
                continue;
            }

            $rawInput = $inputs[$name] ?? null;

            // Appliquer la valeur par défaut si la saisie est absente/nulle
            if (($rawInput === null || $rawInput === '') && $default !== null) {
                if ($type === 'date' && $default === 'aujourd_hui') {
                    $rawInput = now()->format('Y-m-d');
                } else {
                    $rawInput = $default;
                }
            }

            // Gestion de l'option "Toutes..." (envoie NULL)
            if ($type === 'liste' && $optionToutes !== null && (string) $rawInput === (string) $optionToutes) {
                $resolved[$name] = null;
                continue;
            }

            // Contrôle d'obligation
            if ($required && ($rawInput === null || $rawInput === '')) {
                // Pour une liste avec "option_toutes", l'utilisateur a pu choisir "Toutes", ce qui est valide
                if ($type !== 'liste' || $optionToutes === null) {
                    $errors[$name] = sprintf("Le champ [%s] est obligatoire.", $param['libelle'] ?? $name);
                    continue;
                }
            }

            // Si la valeur est vide et non obligatoire, convertir en NULL
            if ($rawInput === null || $rawInput === '') {
                $resolved[$name] = null;
                continue;
            }

            // Conversion et règles spécifiques selon le type
            switch ($type) {
                case 'texte':
                    $val = (string) $rawInput;
                    if (isset($param['longueur_max']) && mb_strlen($val) > (int) $param['longueur_max']) {
                        $errors[$name] = sprintf("Le champ [%s] ne doit pas dépasser %d caractères.", $param['libelle'] ?? $name, $param['longueur_max']);
                    } else {
                        $resolved[$name] = $val;
                    }
                    break;

                case 'nombre':
                    if (! is_numeric($rawInput)) {
                        $errors[$name] = sprintf("Le champ [%s] doit être un nombre.", $param['libelle'] ?? $name);
                    } else {
                        $num = $rawInput + 0;
                        if (isset($param['min']) && $num < $param['min']) {
                            $errors[$name] = sprintf("Le champ [%s] doit être supérieur ou égal à %s.", $param['libelle'] ?? $name, $param['min']);
                        } elseif (isset($param['max']) && $num > $param['max']) {
                            $errors[$name] = sprintf("Le champ [%s] doit être inférieur ou égal à %s.", $param['libelle'] ?? $name, $param['max']);
                        } else {
                            $resolved[$name] = $num;
                        }
                    }
                    break;

                case 'date':
                    $dateStr = (string) $rawInput;
                    if ($dateStr === 'aujourd_hui') {
                        $dateStr = now()->format('Y-m-d');
                    }

                    try {
                        $d = Carbon::createFromFormat('Y-m-d', $dateStr);
                        if (! $d || $d->format('Y-m-d') !== $dateStr) {
                            $errors[$name] = sprintf("Le champ [%s] doit être au format AAAA-MM-JJ.", $param['libelle'] ?? $name);
                        } else {
                            $resolved[$name] = $dateStr;
                        }
                    } catch (\Throwable $e) {
                        $errors[$name] = sprintf("Le champ [%s] doit être une date valide au format AAAA-MM-JJ.", $param['libelle'] ?? $name);
                    }
                    break;

                case 'booleen':
                    $b = filter_var($rawInput, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($b === null && ! in_array($rawInput, [0, 1, '0', '1'], true)) {
                        $errors[$name] = sprintf("Le champ [%s] doit être un booléen (Oui/Non).", $param['libelle'] ?? $name);
                    } else {
                        $resolved[$name] = (bool) $rawInput ? 1 : 0;
                    }
                    break;

                case 'liste':
                    $val = (string) $rawInput;
                    $validValues = $allowedListOptions[$name] ?? null;
                    if (is_array($validValues) && ! in_array($val, $validValues, true)) {
                        $errors[$name] = sprintf("La valeur choisie pour [%s] n'est pas disponible dans la liste.", $param['libelle'] ?? $name);
                    } else {
                        $resolved[$name] = $val;
                    }
                    break;

                default:
                    $resolved[$name] = (string) $rawInput;
                    break;
            }
        }

        // Règle d'antériorité de date (apres_ou_egal)
        foreach ($declaredParams as $param) {
            $name = $param['nom'] ?? null;
            if ($name && ($param['type'] ?? '') === 'date' && ! empty($param['apres_ou_egal'])) {
                $targetName = $param['apres_ou_egal'];
                $startDate = $resolved[$targetName] ?? null;
                $endDate = $resolved[$name] ?? null;

                if ($startDate && $endDate && $startDate > $endDate) {
                    $errors[$name] = sprintf(
                        "La date [%s] (%s) doit être postérieure ou égale à la date [%s] (%s).",
                        $param['libelle'] ?? $name,
                        $endDate,
                        $targetName,
                        $startDate
                    );
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $resolved;
    }
}
