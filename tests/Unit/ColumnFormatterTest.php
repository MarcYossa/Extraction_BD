<?php

namespace Tests\Unit;

use App\Services\Extraction\ColumnFormatter;
use PHPUnit\Framework\TestCase;

class ColumnFormatterTest extends TestCase
{
    protected ColumnFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new ColumnFormatter();
    }

    public function test_format_date_screen_and_csv(): void
    {
        $this->assertEquals('24/09/2026', $this->formatter->formatValue('2026-09-24', 'date', false));
        $this->assertEquals('24/09/2026', $this->formatter->formatValue('2026-09-24', 'date', true));
    }

    public function test_format_nombre_screen_and_csv(): void
    {
        // Écran : séparateur de milliers espace
        $this->assertEquals('1 500', $this->formatter->formatValue(1500, 'nombre', false));

        // CSV : nombre brut
        $this->assertEquals(1500, $this->formatter->formatValue(1500, 'nombre', true));
    }

    public function test_format_montant_screen_and_csv(): void
    {
        // Écran : séparateur de milliers espace et 2 décimales
        $this->assertEquals('12 500,50', $this->formatter->formatValue(12500.5, 'montant', false));

        // CSV : nombre brut float
        $this->assertEquals(12500.5, $this->formatter->formatValue(12500.5, 'montant', true));
    }

    public function test_format_row_with_declared_columns(): void
    {
        $declaredCols = [
            ['cle' => 'num_compte', 'format' => 'code'],
            ['cle' => 'date_ouverture', 'format' => 'date'],
            ['cle' => 'solde', 'format' => 'montant'],
        ];

        $row = [
            'num_compte'     => '000100200',
            'date_ouverture' => '2026-01-15',
            'solde'          => 4500000.75,
        ];

        $formatted = $this->formatter->formatRow($row, $declaredCols, false);

        $this->assertEquals('000100200', $formatted['num_compte']);
        $this->assertEquals('15/01/2026', $formatted['date_ouverture']);
        $this->assertEquals('4 500 000,75', $formatted['solde']);
    }
}
