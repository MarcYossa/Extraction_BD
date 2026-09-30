<?php

namespace App\Services\Extraction;

use InvalidArgumentException;

/**
 * Garde-fou applicatif : n'accepte qu'une requete de LECTURE.
 *
 * Attention : ce n'est qu'une SECONDE ligne de defense. La vraie protection est
 * dans la base (le compte APP_RO n'a que le droit SELECT).
 */
class SqlGuard
{
    private const FORBIDDEN_WORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'MERGE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE',
        'GRANT', 'REVOKE', 'EXECUTE', 'EXEC', 'CALL', 'BEGIN', 'DECLARE', 'COMMIT',
        'ROLLBACK', 'LOCK', 'RENAME', 'COMMENT',
    ];

    public function assertReadOnly(string $sql): void
    {
        // Les commentaires sont refuses : ils servent souvent a masquer du SQL.
        if (str_contains($sql, '--') || str_contains($sql, '/*')) {
            throw new InvalidArgumentException('Les commentaires SQL ne sont pas autorises.');
        }

        $clean = $this->stripLiterals($sql);

        if (!preg_match('/^\s*(SELECT|WITH)\b/i', $clean)) {
            throw new InvalidArgumentException('Seules les requetes SELECT sont autorisees.');
        }

        if (str_contains($clean, ';')) {
            throw new InvalidArgumentException('Une seule instruction est autorisee (pas de point-virgule).');
        }

        if (preg_match('/\bFOR\s+UPDATE\b/i', $clean)) {
            throw new InvalidArgumentException('FOR UPDATE est interdit.');
        }

        $pattern = '/\b(' . implode('|', self::FORBIDDEN_WORDS) . '|DBMS_\w+|UTL_\w+)\b/i';
        if (preg_match($pattern, $clean, $m)) {
            throw new InvalidArgumentException('Mot-cle interdit : ' . strtoupper($m[1]));
        }
    }

    /** Remplace les litteraux texte et les identifiants entre guillemets par des valeurs vides. */
    private function stripLiterals(string $sql): string
    {
        $sql = preg_replace("/'(?:[^']|'')*'/", "''", $sql);

        return preg_replace('/"[^"]*"/', '""', $sql);
    }
}
