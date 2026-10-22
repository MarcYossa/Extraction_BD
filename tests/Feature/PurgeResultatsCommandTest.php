<?php

namespace Tests\Feature;

use App\Services\Extraction\ResultStore;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeResultatsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_purge_resultats_commande_supprime_fichiers_expires(): void
    {
        $resultStore = new ResultStore();
        $resultStore->setOpportunisticPurge(false);

        // Créer un résultat
        $jeton = $resultStore->store(
            utilisateur: 'test.user',
            code: 'TEST_001',
            version: 1,
            parametres: [],
            colonnes: [['cle' => 'id', 'libelle' => 'ID']],
            lignes: [['id' => 1]],
            dureeMs: 10
        );

        // Fichier présent
        $this->assertNotNull($resultStore->get($jeton, 'test.user'));

        // Avancer le temps de 20 minutes (expiration à 15 min)
        Carbon::setTestNow(now()->addMinutes(20));

        // Exécuter la commande
        $exitCode = Artisan::call('app:purge-resultats');

        $this->assertEquals(0, $exitCode);
        $this->assertNull($resultStore->get($jeton, 'test.user'));

        Carbon::setTestNow();
    }
}
