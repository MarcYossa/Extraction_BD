<?php

namespace Tests\Unit;

use App\Services\Extraction\ResultStore;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResultStoreTest extends TestCase
{
    protected ResultStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->store = new ResultStore(Storage::disk('local'));
        $this->store->setOpportunisticPurge(false);
    }

    public function test_peut_stocker_et_recuperer_un_resultat(): void
    {
        $jeton = $this->store->store(
            utilisateur: 'amine.m',
            code: 'CPT_001',
            version: 1,
            parametres: ['agence' => 1],
            colonnes: [['cle' => 'id', 'libelle' => 'Identifiant']],
            lignes: [['id' => 10]],
            dureeMs: 42,
            tronque: false,
            totalLignes: 1,
            parametresAffiches: ['agence' => 'Agence Principale']
        );

        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{40}$/', $jeton);

        $resultat = $this->store->get($jeton, 'amine.m');
        $this->assertNotNull($resultat);
        $this->assertSame('amine.m', $resultat['utilisateur']);
        $this->assertSame('CPT_001', $resultat['code']);
        $this->assertSame(1, $resultat['version']);
        $this->assertSame([['id' => 10]], $resultat['lignes']);
        $this->assertSame(42, $resultat['duree_ms']);
    }

    public function test_acces_refuse_pour_un_autre_utilisateur(): void
    {
        $jeton = $this->store->store(
            utilisateur: 'amine.m',
            code: 'CPT_001',
            version: 1,
            parametres: [],
            colonnes: [],
            lignes: [],
            dureeMs: 10
        );

        $this->assertNull($this->store->get($jeton, 'autre.user'));
    }

    public function test_resultat_expire_apres_15_minutes(): void
    {
        Carbon::setTestNow(now());

        $jeton = $this->store->store(
            utilisateur: 'amine.m',
            code: 'CPT_001',
            version: 1,
            parametres: [],
            colonnes: [],
            lignes: [],
            dureeMs: 10
        );

        $this->assertNotNull($this->store->get($jeton, 'amine.m'));

        // Avancer dans le temps de 16 minutes
        Carbon::setTestNow(now()->addMinutes(16));

        $this->assertNull($this->store->get($jeton, 'amine.m'));
    }

    public function test_purge_des_fichiers_expires(): void
    {
        Carbon::setTestNow(now());

        $jeton1 = $this->store->store(
            utilisateur: 'user1',
            code: 'CPT_001',
            version: 1,
            parametres: [],
            colonnes: [],
            lignes: [],
            dureeMs: 10
        );

        Carbon::setTestNow(now()->addMinutes(16));

        $jeton2 = $this->store->store(
            utilisateur: 'user2',
            code: 'CPT_002',
            version: 1,
            parametres: [],
            colonnes: [],
            lignes: [],
            dureeMs: 10
        );

        $purgeCount = $this->store->purgeExpired();
        $this->assertSame(1, $purgeCount);

        // jeton1 supprimé, jeton2 toujours accessible
        $this->assertNull($this->store->get($jeton1, 'user1'));
        $this->assertNotNull($this->store->get($jeton2, 'user2'));
    }

    public function test_format_de_jeton_invalide_retourne_null(): void
    {
        $this->assertNull($this->store->get('court', 'user1'));
        $this->assertNull($this->store->get('../../../tentative_traversal', 'user1'));
    }

    public function test_suppression_manuelle_dun_resultat(): void
    {
        $jeton = $this->store->store(
            utilisateur: 'user1',
            code: 'CPT_001',
            version: 1,
            parametres: [],
            colonnes: [],
            lignes: [],
            dureeMs: 10
        );

        $this->assertTrue($this->store->delete($jeton));
        $this->assertNull($this->store->get($jeton, 'user1'));
        $this->assertFalse($this->store->delete($jeton));
    }
}
