<?php

namespace App\Http\Controllers\Metier;

use App\Http\Controllers\Controller;
use App\Services\Extraction\CatalogueRepository;
use Illuminate\View\View;

/**
 * Contrôleur du catalogue de l'espace métier (M02).
 * Route : GET /catalogue
 */
class CatalogueController extends Controller
{
    public function __construct(
        private readonly CatalogueRepository $catalogue,
    ) {}

    public function __invoke(): View
    {
        // Toutes les extractions actives, triées par date de publication décroissante
        $actives = $this->catalogue->actives();

        usort($actives, static function (array $a, array $b): int {
            return strcmp($b['publie_le'] ?? '', $a['publie_le'] ?? '');
        });

        return view('metier.catalogue', [
            'extractions' => $actives,
        ]);
    }
}
