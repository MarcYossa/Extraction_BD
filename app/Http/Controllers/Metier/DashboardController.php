<?php

namespace App\Http\Controllers\Metier;

use App\Http\Controllers\Controller;
use App\Services\Extraction\CatalogueRepository;
use Illuminate\View\View;

/**
 * Contrôleur de l'accueil de l'espace métier (M01).
 * Route : GET /dashboard
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly CatalogueRepository $catalogue,
    ) {}

    public function __invoke(): View
    {
        // Extractions actives triées du plus récemment publié au plus ancien (3 premières)
        $actives = $this->catalogue->actives();

        // Tri par publié_le décroissant
        usort($actives, static function (array $a, array $b): int {
            return strcmp($b['publie_le'] ?? '', $a['publie_le'] ?? '');
        });

        $recentes = array_slice($actives, 0, 3);

        return view('metier.dashboard', [
            'recentes' => $recentes,
        ]);
    }
}
