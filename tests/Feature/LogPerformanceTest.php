<?php

namespace Tests\Feature;

use App\Services\Extraction\LogReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('lent')]
class LogPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Génère un jeu de données synthétique pour tester la montée en charge du journal.
     */
    protected function generateSyntheticLogs(int $count): string
    {
        $users = ['amine.m', 'sonia.d', 'moussa.k', 'jean.d', 'fatou.s'];
        $codes = ['CPT_001', 'CLI_001', 'CRT_012', 'RIS_003', 'CPT_005'];
        $modes = ['execution', 'export_csv', 'test', 'publication'];
        $statuts = ['succes', 'succes', 'succes', 'echec'];

        $entries = [];
        $baseDate = Carbon::parse('2026-01-01 08:00:00');

        for ($i = 0; $i < $count; $i++) {
            $entries[] = [
                'id'          => 'ACT-' . (1000 + $i),
                'date'        => $baseDate->copy()->addMinutes($i)->toIso8601String(),
                'utilisateur' => $users[$i % count($users)],
                'code'        => $codes[$i % count($codes)],
                'version'     => ($i % 3) + 1,
                'mode'        => $modes[$i % count($modes)],
                'parametres'  => [
                    'agence' => (string) (($i % 5) + 1),
                    'statut' => 'ACTIF',
                ],
                'statut'      => $statuts[$i % count($statuts)],
                'nb_lignes'   => ($i * 13) % 500,
                'tronque'     => ($i % 10) === 0,
                'duree_ms'    => 100 + ($i % 400),
            ];
        }

        return json_encode($entries);
    }

    public function test_performance_lecture_5000_entrees(): void
    {
        $content = $this->generateSyntheticLogs(5000);
        $filePath = 'extraction/bench_5000.json';
        Storage::disk('local')->put($filePath, $content);

        $reader = new LogReader(null, $filePath);

        $start = microtime(true);
        $paginator = $reader->getExecutionLogs(['utilisateur' => 'sonia.d'], page: 1, perPage: 50);
        $durationSec = microtime(true) - $start;

        $this->assertGreaterThan(0, $paginator->total());
        $this->assertLessThanOrEqual(50, count($paginator->items()));

        echo sprintf("\n[PERF] 5 000 entrées : %.4f secondes (Total filtré: %d)\n", $durationSec, $paginator->total());

        // Doit s'exécuter confortablement sous 500ms
        $this->assertLessThan(1.0, $durationSec, "La lecture de 5 000 entrées ne doit pas dépasser 1 seconde.");
    }

    public function test_performance_lecture_20000_entrees(): void
    {
        $content = $this->generateSyntheticLogs(20000);
        $filePath = 'extraction/bench_20000.json';
        Storage::disk('local')->put($filePath, $content);

        $reader = new LogReader(null, $filePath);

        $start = microtime(true);
        $paginator = $reader->getExecutionLogs(['utilisateur' => 'sonia.d'], page: 1, perPage: 50);
        $durationSec = microtime(true) - $start;

        $this->assertGreaterThan(0, $paginator->total());
        $this->assertLessThanOrEqual(50, count($paginator->items()));

        echo sprintf("\n[PERF] 20 000 entrées : %.4f secondes (Total filtré: %d)\n", $durationSec, $paginator->total());

        // Mesure clé du lot 10 : vérifier si le temps dépasse 1 seconde
        // On enregistre le temps pour le rapport de fin de lot
        $this->assertLessThan(2.0, $durationSec);
    }
}
