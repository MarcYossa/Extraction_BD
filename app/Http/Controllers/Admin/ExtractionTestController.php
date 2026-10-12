<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\ExecutionLogger;
use App\Services\Extraction\ExtractionRunner;
use App\Services\Extraction\ListOptionsProvider;
use App\Services\Extraction\ParameterResolver;
use App\Services\Extraction\QueryCompiler;
use App\Services\Extraction\ResultStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExtractionTestController extends Controller
{
    /**
     * GET /admin/extractions/{code}/test
     * Écran S06 : Saisie des paramètres d'exécution avant publication.
     */
    public function testForm(
        string $code,
        CatalogueRepository $repository,
        ListOptionsProvider $optionsProvider
    ): View {
        $extraction = $repository->find($code);
        if (! $extraction) {
            abort(404, "Extraction introuvable : {$code}");
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

        return view('admin.extractions.test', [
            'extraction'     => $extraction,
            'optionsByParam' => $optionsByParam,
            'values'         => session()->get('old_inputs', []),
        ]);
    }

    /**
     * POST /admin/extractions/{code}/test
     */
    public function executeTest(string $code)
    {
        // Implémenté à l'étape suivante
        return redirect()->route('admin.extractions.test', ['code' => $code]);
    }
}
