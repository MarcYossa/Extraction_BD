<?php

namespace App\Services\Extraction;

use Throwable;

/**
 * Nettoie et formate les messages d'erreurs Oracle pour les administrateurs.
 *
 * Règle de sécurité :
 * - Affiche uniquement le code Oracle (ex. ORA-01861) et la première ligne du message d'erreur.
 * - Ne doit JAMAIS exposer la requête SQL, les valeurs liées (bindings) ou les chemins de fichiers système.
 * - En l'absence de code ORA identifiable, retourne un message d'erreur générique sécurisé.
 */
class OracleErrorSanitizer
{
    /**
     * Nettoie une exception ou un message d'erreur Oracle.
     */
    public static function sanitize(Throwable|string $error): string
    {
        $raw = $error instanceof Throwable ? $error->getMessage() : $error;

        // 1. Recherche du motif ORA-XXXXX
        if (! preg_match('/(ORA-\d{4,5}\s*:[^\r\n]*)/i', $raw, $matches)) {
            // Recherche alternative si préfixé par "Error Message :"
            if (! preg_match('/Error Message\s*:\s*(ORA-\d{4,5}\s*:[^\r\n]*)/i', $raw, $matches)) {
                return "Une erreur est survenue lors de l'exécution du test sur la base de données.";
            }
        }

        $line = trim($matches[1]);

        // 2. Supprimer toute clause SQL embarquée (ex: (SQL: SELECT ...))
        $line = preg_replace('/\s*\(SQL\s*:.*$/i', '', $line);
        $line = preg_replace('/\s*Statement\s*:.*$/i', '', $line);
        $line = preg_replace('/\s*Position\s*:.*$/i', '', $line);

        // 3. Supprimer les valeurs liées (ex: (Bindings: [...]), [1, 2], etc.)
        $line = preg_replace('/\s*\(Bindings\s*:.*$/i', '', $line);
        $line = preg_replace('/\s*Bindings\s*:.*$/i', '', $line);
        $line = preg_replace('/\s*\[.*?\]$/', '', $line);

        // 4. Supprimer les chemins de fichiers et parenthèses système
        // Ex: (ext\pdo_oci\oci_statement.c:166), in /path/to/file.php:42, in C:\...
        $line = preg_replace('/\s*\([^)]*[\/\\\.].*?\)/', '', $line);
        $line = preg_replace('/\s*in\s+[A-Za-z]:[^\r\n]*/', '', $line);
        $line = preg_replace('/\s*in\s+\/[^\r\n]*/', '', $line);
        $line = preg_replace('/[a-zA-Z0-9_\-\.\/\\\\]+\.(?:php|c|h|cpp|sql)(?::\d+)?/i', '', $line);

        // 5. Supprimer la ponctuation ou les séparateurs orphelins en fin de chaîne
        $line = trim($line);
        $line = rtrim($line, " \t\n\r\0\x0B,:;-");

        // 6. Normaliser le format sous la forme : "ORA-XXXXX : message"
        if (preg_match('/^(ORA-\d{4,5})\s*:\s*(.+)$/i', $line, $parts)) {
            $code = strtoupper($parts[1]);
            $msg = trim($parts[2]);
            return "{$code} : {$msg}";
        }

        return $line;
    }
}
