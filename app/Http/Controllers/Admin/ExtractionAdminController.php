<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Extraction\CatalogueRepository;
use Illuminate\Http\Request;

/**
 * Écran S03 – Liste des extractions (espace administration).
 */
class ExtractionAdminController extends Controller
{
    public function __construct(protected CatalogueRepository $repository) {}

    /**
     * GET /admin/extractions
     * Paramètres GET : q (recherche code/titre) et statut (filtre de statut).
     */
    public function index(Request $request): \Illuminate\View\View
    {
        $q      = trim((string) $request->query('q', ''));
        $statut = trim((string) $request->query('statut', ''));

        $extractions = $this->repository->all();

        // Filtre texte (code ou titre, insensible à la casse)
        if ($q !== '') {
            $extractions = array_values(array_filter($extractions, function (array $ext) use ($q): bool {
                return str_contains(strtoupper($ext['code'] ?? ''), strtoupper($q))
                    || str_contains(strtoupper($ext['titre'] ?? ''), strtoupper($q));
            }));
        }

        // Filtre statut
        if ($statut !== '' && strtolower($statut) !== 'tous') {
            $extractions = array_values(array_filter($extractions, function (array $ext) use ($statut): bool {
                return strcasecmp($ext['statut'] ?? '', $statut) === 0;
            }));
        }

        // Tri par date de modification décroissante
        usort($extractions, function (array $a, array $b): int {
            return strcmp($b['modifie_le'] ?? '', $a['modifie_le'] ?? '');
        });

        $total = count($this->repository->all());

        return view('admin.extractions.index', compact('extractions', 'q', 'statut', 'total'));
    }

    /**
     * PATCH /admin/extractions/{code}/statut  (D22 – cycle de vie)
     * Permet de désactiver (active → inactive) ou de réactiver (inactive → active).
     */
    public function toggleStatut(string $code): \Illuminate\Http\RedirectResponse
    {
        $extraction = $this->repository->find($code);

        if (! $extraction) {
            abort(404);
        }

        $statutActuel = strtolower($extraction['statut'] ?? 'brouillon');

        if ($statutActuel === 'active') {
            $extraction['statut'] = 'inactive';
            $message = "L'extraction {$code} a été désactivée.";
        } elseif ($statutActuel === 'inactive') {
            $extraction['statut'] = 'active';
            $message = "L'extraction {$code} a été réactivée.";
        } else {
            return redirect()->route('admin.extractions.index')
                ->with('error', "Le statut d'un brouillon ne peut pas être modifié depuis la liste.");
        }

        $this->repository->save($extraction, auth()->user()->identifiant);

        return redirect()->route('admin.extractions.index')
            ->with('success', $message);
    }
}
