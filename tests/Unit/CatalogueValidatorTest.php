<?php

namespace Tests\Unit;

use App\Services\Extraction\CatalogueValidator;
use Tests\TestCase;

class CatalogueValidatorTest extends TestCase
{
    protected CatalogueValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new CatalogueValidator();
    }

    /**
     * Valide le catalogue d'exemple valide (queries.example.json).
     */
    public function test_validates_example_catalogue_successfully(): void
    {
        $examplePath = base_path('docs/design/catalogue/queries.example.json');
        $this->assertFileExists($examplePath);

        $data = json_decode(file_get_contents($examplePath), true);
        $errors = $this->validator->validateCatalogue($data);

        $this->assertEmpty($errors, 'Le catalogue d\'exemple doit être totalement valide. Erreurs : ' . implode(' | ', $errors));
    }

    /**
     * Détecte un paramètre non déclaré dans le SQL (Règle 3).
     */
    public function test_detects_undeclared_sql_parameter(): void
    {
        $extraction = [
            'code' => 'TEST_001',
            'titre' => 'Test extractions',
            'description' => 'Description test',
            'statut' => 'brouillon',
            'version' => 1,
            'requete_sql' => ['SELECT * FROM amp_test.agence WHERE agence_id = :inconnu'],
            'parametres' => [],
            'colonnes' => [],
            'cree_le' => '2026-01-01T10:00:00+01:00',
            'cree_par' => 'amine.m',
            'modifie_le' => '2026-01-01T10:00:00+01:00',
            'modifie_par' => 'amine.m',
        ];

        $errors = $this->validator->validateExtraction($extraction);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Règle 3', $errors[0]);
    }

    /**
     * Détecte un mot-clé SQL interdit (Règle 4).
     */
    public function test_detects_forbidden_sql_keyword(): void
    {
        $extraction = [
            'code' => 'TEST_002',
            'titre' => 'Test SQL Interdit',
            'description' => 'Description test',
            'statut' => 'brouillon',
            'version' => 1,
            'requete_sql' => ['DELETE FROM amp_test.agence'],
            'parametres' => [],
            'colonnes' => [],
            'cree_le' => '2026-01-01T10:00:00+01:00',
            'cree_par' => 'amine.m',
            'modifie_le' => '2026-01-01T10:00:00+01:00',
            'modifie_par' => 'amine.m',
        ];

        $errors = $this->validator->validateExtraction($extraction);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('SqlGuard', $errors[0]);
    }

    /**
     * Détecte le passage au statut active sans test réussi (Règle 9).
     */
    public function test_detects_active_status_without_successful_test(): void
    {
        $extraction = [
            'code' => 'TEST_003',
            'titre' => 'Test Statut Active',
            'description' => 'Description test',
            'statut' => 'active',
            'version' => 2,
            'requete_sql' => ['SELECT agence_id AS agence FROM amp_test.agence'],
            'parametres' => [],
            'colonnes' => [['cle' => 'agence', 'libelle' => 'Agence', 'format' => 'code']],
            'cree_le' => '2026-01-01T10:00:00+01:00',
            'cree_par' => 'amine.m',
            'modifie_le' => '2026-01-01T10:00:00+01:00',
            'modifie_par' => 'amine.m',
            'dernier_test' => null,
        ];

        $errors = $this->validator->validateExtraction($extraction);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Règle 9', $errors[0]);
    }
}
