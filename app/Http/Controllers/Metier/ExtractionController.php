<?php

namespace App\Http\Controllers\Metier;

use App\Http\Controllers\Controller;
use App\Services\Extraction\CatalogueRepository;
use Illuminate\View\View;

/**
 * Contrôleur provisoire pour la fiche d'une extraction métier.
 * Route : GET /extractions/{code}
 *
 * Si l'extraction existe et est active → page "Bientôt disponible" (lot 8).
 * Sinon → 404.
 */
class ExtractionController extends Controller
{
    public function __construct(
        private readonly CatalogueRepository $catalogue,
    ) {}

    public function show(string $code): View
    {
        $extraction = $this->catalogue->find($code);

        if (! $extraction || ($extraction['statut'] ?? '') !== 'active') {
            abort(404);
        }

        return view('metier.extraction-bientot', [
            'extraction' => $extraction,
        ]);
    }
}
