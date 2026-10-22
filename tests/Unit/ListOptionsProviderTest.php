<?php

namespace Tests\Unit;

use App\Services\Extraction\ListOptionsProvider;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ListOptionsProviderTest extends TestCase
{
    public function test_retourne_tableau_vide_si_non_liste(): void
    {
        $provider = new ListOptionsProvider();

        $this->assertEquals([], $provider->getOptions(['type' => 'texte']));
        $this->assertEquals([], $provider->getOptions(['type' => 'liste']));
    }

    public function test_charge_options_fixes(): void
    {
        $provider = new ListOptionsProvider();

        $param = [
            'nom'    => 'statut',
            'type'   => 'liste',
            'source' => [
                'type'    => 'fixe',
                'options' => [
                    ['valeur' => 'ACTIF', 'libelle' => 'Actif'],
                    ['valeur' => 'BLOQUE', 'libelle' => 'Bloqué'],
                ],
            ],
        ];

        $options = $provider->getOptions($param);

        $this->assertCount(2, $options);
        $this->assertEquals('ACTIF', $options[0]['valeur']);
        $this->assertEquals('Actif', $options[0]['libelle']);
        $this->assertEquals('BLOQUE', $options[1]['valeur']);
        $this->assertEquals('Bloqué', $options[1]['libelle']);
    }

    public function test_charge_options_requete_oracle(): void
    {
        try {
            DB::connection('oracle')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Base Oracle non disponible.');
        }

        $provider = new ListOptionsProvider();

        $param = [
            'nom'    => 'agence',
            'type'   => 'liste',
            'source' => [
                'type'        => 'requete',
                'requete_sql' => [
                    'SELECT agence_id AS valeur, nom_agence AS libelle',
                    'FROM amp_test.agence',
                    'ORDER BY nom_agence',
                ],
            ],
        ];

        $options = $provider->getOptions($param);

        $this->assertNotEmpty($options);
        $this->assertArrayHasKey('valeur', $options[0]);
        $this->assertArrayHasKey('libelle', $options[0]);
    }
}
