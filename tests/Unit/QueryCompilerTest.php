<?php

namespace Tests\Unit;

use App\Services\Extraction\QueryCompiler;
use PHPUnit\Framework\TestCase;

class QueryCompilerTest extends TestCase
{
    protected QueryCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new QueryCompiler();
    }

    /**
     * Test de compilation de l'exemple CLI_001 avec 6 marqueurs liés dans l'ordre.
     */
    public function test_cli_001_example_compilation(): void
    {
        $sqlLines = [
            "SELECT * FROM amp_test.client cl",
            "WHERE (:agence IS NULL OR cl.agence_id = :agence)",
            "  AND (:statut IS NULL OR cl.statut = :statut)",
            "  AND (:nom IS NULL OR UPPER(cl.nom) LIKE UPPER(:nom) || '%')",
        ];

        $declaredParams = [
            ['nom' => 'agence', 'type' => 'liste'],
            ['nom' => 'statut', 'type' => 'liste'],
            ['nom' => 'nom', 'type' => 'texte'],
        ];

        $resolvedValues = [
            'agence' => '1',
            'statut' => null,
            'nom'    => 'tchou',
        ];

        $result = $this->compiler->compile($sqlLines, $declaredParams, $resolvedValues);

        $this->assertStringContainsString('(? IS NULL OR cl.agence_id = ?)', $result['sql']);
        $this->assertStringContainsString('(? IS NULL OR cl.statut = ?)', $result['sql']);
        $this->assertStringContainsString('(? IS NULL OR UPPER(cl.nom) LIKE UPPER(?) || \'%\')', $result['sql']);

        // 6 variables liées dans l'ordre exact : ["1", "1", null, null, "tchou", "tchou"]
        $this->assertEquals(['1', '1', null, null, 'tchou', 'tchou'], $result['bindings']);
    }

    /**
     * Test : les marqueurs entre apostrophes (ex: '12:30') ne doivent pas être remplacés.
     */
    public function test_colon_inside_single_quotes_is_ignored(): void
    {
        $sql = "SELECT * FROM t WHERE col = 'heure:12:30' AND param = :valeur";

        $declaredParams = [
            ['nom' => 'valeur', 'type' => 'texte'],
        ];

        $result = $this->compiler->compile($sql, $declaredParams, ['valeur' => 'test']);

        $this->assertStringContainsString("'heure:12:30'", $result['sql']);
        $this->assertStringContainsString('param = ?', $result['sql']);
        $this->assertEquals(['test'], $result['bindings']);
    }

    /**
     * Test : les paramètres de type 'date' sont enveloppés dans TO_DATE(?, 'YYYY-MM-DD').
     */
    public function test_date_parameter_wrapped_in_to_date(): void
    {
        $sql = "WHERE d.date_ouverture BETWEEN :date_debut AND :date_fin";

        $declaredParams = [
            ['nom' => 'date_debut', 'type' => 'date'],
            ['nom' => 'date_fin', 'type' => 'date'],
        ];

        $result = $this->compiler->compile($sql, $declaredParams, [
            'date_debut' => '2026-01-01',
            'date_fin'   => '2026-12-31',
        ]);

        $this->assertStringContainsString("BETWEEN TO_DATE(?, 'YYYY-MM-DD') AND TO_DATE(?, 'YYYY-MM-DD')", $result['sql']);
        $this->assertEquals(['2026-01-01', '2026-12-31'], $result['bindings']);
    }
}
