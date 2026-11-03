<?php

namespace Tests\Unit;

use App\Services\Extraction\OracleErrorSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OracleErrorSanitizerTest extends TestCase
{
    #[DataProvider('oracleErrorsDataProvider')]
    public function test_sanitizes_oracle_errors_properly(string $input, string $expected): void
    {
        $sanitized = OracleErrorSanitizer::sanitize($input);
        $this->assertSame($expected, $sanitized);
    }

    public static function oracleErrorsDataProvider(): array
    {
        return [
            'simple_ora_error' => [
                'ORA-01861: literal does not match format string',
                'ORA-01861 : literal does not match format string',
            ],
            'french_ora_error' => [
                'ORA-01861 : le littéral ne correspond pas au format',
                'ORA-01861 : le littéral ne correspond pas au format',
            ],
            'pdo_error_with_c_file_path' => [
                'SQLSTATE[HY000]: General error: 1861 OCIStmtExecute: ORA-01861: literal does not match format string (ext\pdo_oci\oci_statement.c:166)',
                'ORA-01861 : literal does not match format string',
            ],
            'multiline_error_with_sql_and_bindings' => [
                "Error Code : 904\nError Message : ORA-00904: \"INVALID_COL\": invalid identifier\nPosition : 8\nStatement : SELECT INVALID_COL FROM amp_test.compte\nBindings : []",
                'ORA-00904 : "INVALID_COL": invalid identifier',
            ],
            'inline_sql_clause_and_php_path' => [
                "ORA-00942: table or view does not exist (SQL: SELECT * FROM unknown_table WHERE id = :p1) (Bindings: [1]) in E:\\Documents\\Projets_BCPME\\vendor\\laravel\\framework\\Connection.php:412",
                'ORA-00942 : table or view does not exist',
            ],
            'multiline_stack_takes_first_line_only' => [
                "ORA-01476: divisor is equal to zero\nORA-06512: at \"AMP_TEST.PKG_TEST\", line 42",
                'ORA-01476 : divisor is equal to zero',
            ],
            'timeout_ora_01013' => [
                'ORA-01013: user requested cancel of current operation',
                'ORA-01013 : user requested cancel of current operation',
            ],
            'generic_exception_without_ora_code' => [
                'Connection could not be established: Network error',
                "Une erreur est survenue lors de l'exécution du test sur la base de données.",
            ],
        ];
    }

    public function test_sanitizes_throwable_instance(): void
    {
        $exception = new \RuntimeException("Simulation ORA-01861: literal does not match format string");
        $result = OracleErrorSanitizer::sanitize($exception);

        $this->assertSame('ORA-01861 : literal does not match format string', $result);
    }
}
