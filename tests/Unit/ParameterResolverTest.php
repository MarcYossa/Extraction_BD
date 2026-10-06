<?php

namespace Tests\Unit;

use App\Services\Extraction\ParameterResolver;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ParameterResolverTest extends TestCase
{
    protected ParameterResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ParameterResolver();
    }

    public function test_resolves_valid_text_parameter(): void
    {
        $params = [['nom' => 'nom', 'type' => 'texte', 'obligatoire' => false, 'longueur_max' => 50]];
        $result = $this->resolver->resolve($params, ['nom' => 'Tchoupo']);
        $this->assertEquals('Tchoupo', $result['nom']);
    }

    public function test_throws_when_text_exceeds_max_length(): void
    {
        $this->expectException(ValidationException::class);
        $params = [['nom' => 'nom', 'type' => 'texte', 'obligatoire' => true, 'longueur_max' => 5]];
        $this->resolver->resolve($params, ['nom' => 'Tchoupo']);
    }

    public function test_resolves_valid_number_parameter(): void
    {
        $params = [['nom' => 'montant', 'type' => 'nombre', 'min' => 10, 'max' => 100]];
        $result = $this->resolver->resolve($params, ['montant' => 50]);
        $this->assertEquals(50, $result['montant']);
    }

    public function test_throws_when_number_out_of_bounds(): void
    {
        $this->expectException(ValidationException::class);
        $params = [['nom' => 'montant', 'type' => 'nombre', 'min' => 10, 'max' => 100]];
        $this->resolver->resolve($params, ['montant' => 5]);
    }

    public function test_resolves_date_parameter_and_default_aujourd_hui(): void
    {
        $params = [['nom' => 'date_fin', 'type' => 'date', 'defaut' => 'aujourd_hui']];
        $result = $this->resolver->resolve($params, []);
        $this->assertEquals(now()->format('Y-m-d'), $result['date_fin']);
    }

    public function test_throws_when_end_date_before_start_date(): void
    {
        $this->expectException(ValidationException::class);
        $params = [
            ['nom' => 'date_debut', 'type' => 'date'],
            ['nom' => 'date_fin', 'type' => 'date', 'apres_ou_egal' => 'date_debut'],
        ];
        $this->resolver->resolve($params, [
            'date_debut' => '2026-12-31',
            'date_fin'   => '2026-01-01',
        ]);
    }

    public function test_resolves_boolean_parameter_to_1_or_0(): void
    {
        $params = [['nom' => 'actif', 'type' => 'booleen']];
        $resTrue = $this->resolver->resolve($params, ['actif' => true]);
        $resFalse = $this->resolver->resolve($params, ['actif' => '0']);

        $this->assertEquals(1, $resTrue['actif']);
        $this->assertEquals(0, $resFalse['actif']);
    }

    public function test_resolves_list_option_toutes_to_null(): void
    {
        $params = [[
            'nom' => 'agence',
            'type' => 'liste',
            'obligatoire' => true,
            'option_toutes' => '— Toutes les agences —',
        ]];

        $result = $this->resolver->resolve($params, ['agence' => '— Toutes les agences —']);
        $this->assertNull($result['agence']);
    }

    public function test_throws_when_list_option_is_invalid(): void
    {
        $this->expectException(ValidationException::class);
        $params = [['nom' => 'agence', 'type' => 'liste', 'obligatoire' => true]];
        $allowed = ['agence' => ['1', '2', '3']];

        $this->resolver->resolve($params, ['agence' => '999'], $allowed);
    }
}
