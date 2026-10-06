<?php

namespace App\Http\Controllers;

use App\Services\Extraction\CatalogueRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Écran S02 – Accueil administrateur technique.
 */
class AdminHomeController extends Controller
{
    public function __construct(protected CatalogueRepository $repository) {}

    public function __invoke(Request $request): \Illuminate\View\View
    {
        // Initialise queries.json depuis l'exemple s'il n'existe pas encore
        $this->seedCatalogueIfMissing();

        // Les 5 extractions les plus récemment modifiées
        $all = $this->repository->all();

        usort($all, function (array $a, array $b): int {
            return strcmp($b['modifie_le'] ?? '', $a['modifie_le'] ?? '');
        });

        $dernieres = array_slice($all, 0, 5);

        return view('admin.home', compact('dernieres'));
    }

    /**
     * Copie queries.example.json vers storage/app/private/extraction/queries.json
     * si le fichier n'existe pas encore.
     */
    private function seedCatalogueIfMissing(): void
    {
        $disk = Storage::disk('local');
        $path = config('extraction.queries_file', 'extraction/queries.json');

        if (! $disk->exists($path)) {
            $example = base_path('docs/design/catalogue/queries.example.json');
            if (file_exists($example)) {
                $disk->makeDirectory(dirname($path));
                $disk->put($path, file_get_contents($example));
            }
        }
    }
}
