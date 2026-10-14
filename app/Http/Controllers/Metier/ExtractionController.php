<?php

namespace App\Http\Controllers\Metier;

use App\Http\Controllers\Controller;
use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\ListOptionsProvider;
use App\Services\Extraction\ResultStore;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExtractionController extends Controller
{
    public function __construct(
        private readonly CatalogueRepository $catalogue,
    ) {}

    /**
     * GET /extractions/{code}
     * Écran M03 : Saisie des paramètres d'exécution d'une extraction active.
     */
    public function show(
        Request $request,
        string $code,
        ListOptionsProvider $optionsProvider,
        ResultStore $resultStore
    ): View {
        $extraction = $this->catalogue->find($code);

        if (! $extraction || ($extraction['statut'] ?? '') !== 'active') {
            abort(404);
        }

        $parametres = $extraction['parametres'] ?? [];
        $optionsByParam = [];

        foreach ($parametres as $param) {
            if (($param['type'] ?? '') === 'liste') {
                try {
                    $optionsByParam[$param['nom']] = $optionsProvider->getOptions($param);
                } catch (\Throwable $e) {
                    $optionsByParam[$param['nom']] = [];
                }
            }
        }

        // Pré-remplissage : session (old_inputs), ancien résultat par jeton, ou vide
        $values = session()->get('old_inputs', []);
        $jeton = $request->query('jeton');
        if (empty($values) && $jeton && is_string($jeton)) {
            $prevResult = $resultStore->get($jeton, auth()->user()->identifiant);
            if ($prevResult && isset($prevResult['parametres'])) {
                $values = $prevResult['parametres'];
            }
        }

        return view('metier.extractions.show', [
            'extraction'     => $extraction,
            'optionsByParam' => $optionsByParam,
            'values'         => $values,
        ]);
    }

    /**
     * POST /extractions/{code}/execute
     * Exécution d'une extraction avec paramètres.
     */
    public function execute(Request $request, string $code)
    {
        // Sera implémenté à l'étape suivante
    }

    /**
     * GET /extractions/{code}/resultats/{jeton}
     * Affichage du résultat M04.
     */
    public function resultats(Request $request, string $code, string $jeton)
    {
        // Sera implémenté à l'étape suivante
    }

    /**
     * GET /extractions/{code}/csv/{jeton}
     * Export CSV du résultat conservé.
     */
    public function csv(Request $request, string $code, string $jeton)
    {
        // Sera implémenté à l'étape suivante
    }
}


