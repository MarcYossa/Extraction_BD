<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\ExecutionLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExtractionStatutController extends Controller
{
    /**
     * PATCH /admin/extractions/{code}/statut
     * Gestion du cycle de vie : publication, désactivation, réactivation (D22).
     */
    public function updateStatut(
        Request $request,
        string $code,
        CatalogueRepository $repository,
        ExecutionLogger $logger
    ): RedirectResponse {
        $extraction = $repository->find($code);
        if (! $extraction) {
            abort(404, "Extraction introuvable : {$code}");
        }

        $user = auth()->user()->identifiant;
        $version = (int) ($extraction['version'] ?? 1);
        $statutActuel = strtolower($extraction['statut'] ?? 'brouillon');
        $action = $request->input('action');

        // Bascule automatique si aucune action n'est spécifiée
        if (! $action) {
            if ($statutActuel === 'active') {
                $action = 'desactiver';
            } elseif ($statutActuel === 'inactive') {
                $action = 'reactiver';
            } else {
                return back()->with('error', "Une extraction en brouillon doit être testée avec succès avant d'être publiée.");
            }
        }

        $dernierTest = $extraction['dernier_test'] ?? null;
        $testValide = $dernierTest
            && ($dernierTest['resultat'] ?? '') === 'reussi'
            && (int) ($dernierTest['version'] ?? 0) === $version;

        switch ($action) {
            case 'publier':
                if (! $testValide) {
                    return back()->with('error', "Impossible de publier l'extraction {$code} : un test réussi de la version courante (v{$version}) est obligatoire.");
                }

                $extraction['statut'] = 'active';
                $extraction['publie_le'] = now()->toIso8601String();
                $extraction['publie_par'] = $user;
                $message = "L'extraction {$code} a été publiée avec succès et est désormais disponible dans le catalogue métier.";

                $logger->record([
                    'utilisateur' => $user,
                    'code'        => $code,
                    'version'     => $version,
                    'mode'        => 'publication',
                    'statut'      => 'succes',
                ]);
                break;

            case 'desactiver':
                if ($statutActuel === 'brouillon') {
                    return back()->with('error', "Une extraction en brouillon ne peut pas être désactivée.");
                }

                $extraction['statut'] = 'inactive';
                $message = "L'extraction {$code} a été désactivée et retirée du catalogue métier.";

                $logger->record([
                    'utilisateur' => $user,
                    'code'        => $code,
                    'version'     => $version,
                    'mode'        => 'desactivation',
                    'statut'      => 'succes',
                ]);
                break;

            case 'reactiver':
                if (! $testValide) {
                    return back()->with('error', "Impossible de réactiver l'extraction {$code} : aucun test réussi n'est enregistré pour la version courante (v{$version}).");
                }

                $extraction['statut'] = 'active';
                $message = "L'extraction {$code} a été réactivée avec succès.";

                $logger->record([
                    'utilisateur' => $user,
                    'code'        => $code,
                    'version'     => $version,
                    'mode'        => 'reactivation',
                    'statut'      => 'succes',
                ]);
                break;

            default:
                return back()->with('error', "Action '{$action}' non reconnue.");
        }

        $repository->save($extraction, $user);

        return redirect()->route('admin.extractions.index')->with('success', $message);
    }
}
