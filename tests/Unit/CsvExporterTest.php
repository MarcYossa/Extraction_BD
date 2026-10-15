<?php

namespace Tests\Unit;

use App\Services\Extraction\CsvExporter;
use PHPUnit\Framework\TestCase;

class CsvExporterTest extends TestCase
{
    protected CsvExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = new CsvExporter();
    }

    /**
     * Vérifie la neutralisation des formules Excel (injection CSV).
     */
    public function test_csv_safe_neutralise_les_formules_excel(): void
    {
        // Les cellules commençant par =, +, -, @, \t, \r doivent être préfixées par '
        $this->assertSame("'=SUM(1,2)", $this->exporter->csvSafe("=SUM(1,2)"));
        $this->assertSame("'+12345", $this->exporter->csvSafe("+12345"));
        $this->assertSame("'-500", $this->exporter->csvSafe("-500"));
        $this->assertSame("'@evil", $this->exporter->csvSafe("@evil"));
        $this->assertSame("'\ttab", $this->exporter->csvSafe("\ttab"));

        // Les textes normaux et nombres ne sont pas modifiés
        $this->assertSame("Texte normal", $this->exporter->csvSafe("Texte normal"));
        $this->assertSame(12345, $this->exporter->csvSafe(12345));
        $this->assertSame(12.50, $this->exporter->csvSafe(12.50));
        $this->assertNull($this->exporter->csvSafe(null));
    }

    /**
     * Vérifie la génération de la réponse CSV en streaming.
     */
    public function test_stream_csv_contient_bom_separateur_et_en_tetes(): void
    {
        $resultat = [
            'code'       => 'TEST_001',
            'colonnes'   => [
                ['cle' => 'id_client', 'libelle' => 'N° Client', 'format' => 'code'],
                ['cle' => 'nom_client', 'libelle' => 'Nom du Client', 'format' => 'texte'],
                ['cle' => 'solde', 'libelle' => 'Solde', 'format' => 'montant'],
                ['cle' => 'date_ouv', 'libelle' => 'Date Ouverture', 'format' => 'date'],
                ['cle' => 'formule', 'libelle' => 'Formule', 'format' => 'texte'],
            ],
            'raw_lignes' => [
                [
                    'id_client'  => 'CLI001',
                    'nom_client' => 'Hervé Nguema',
                    'solde'      => 1500.50,
                    'date_ouv'   => '2023-05-14',
                    'formule'    => '=SUM(1, 2)',
                ],
                [
                    'id_client'  => 'CLI002',
                    'nom_client' => 'Société Ébène',
                    'solde'      => 0,
                    'date_ouv'   => '2024-01-01',
                    'formule'    => 'Normal',
                ],
            ],
        ];

        $response = $this->exporter->download($resultat, 'test_export.csv');

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="test_export.csv"', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // 1. Présence du BOM UTF-8 (\xEF\xBB\xBF) au tout début du contenu
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // 2. Séparateur point-virgule et en-têtes
        $lines = explode("\n", trim($content));
        $this->assertCount(3, $lines); // En-tête + 2 lignes

        $headerLine = substr($lines[0], 3); // retire les 3 octets du BOM
        $this->assertSame('"N° Client";"Nom du Client";Solde;"Date Ouverture";Formule', $headerLine);

        // 3. Ligne 1 : formats vérifiés
        $this->assertStringContainsString('Hervé Nguema', $lines[1]);
        $this->assertStringContainsString('14/05/2023', $lines[1]); // Date jj/mm/aaaa
        $this->assertStringContainsString("1500.5", $lines[1]); // Nombre brut
        $this->assertStringContainsString("'=SUM(1, 2)", $lines[1]); // Neutralisation de formule protégée

        // 4. Ligne 2 : accents vérifiés
        $this->assertStringContainsString('Société Ébène', $lines[2]);
        $this->assertStringContainsString('01/01/2024', $lines[2]);
    }
}
