<?php

namespace App\Console\Commands;

use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\CatalogueValidator;
use Illuminate\Console\Command;

class ValiderCatalogue extends Command
{
    /**
     * Le nom et la signature de la commande.
     *
     * @var string
     */
    protected $signature = 'catalogue:valider
                            {--fichier= : Chemin relatif ou absolu du fichier catalogue à valider}';

    /**
     * La description de la commande.
     *
     * @var string
     */
    protected $description = 'Valide la conformité du catalogue d\'extractions (schéma JSON et règles croisées).';

    public function handle(CatalogueValidator $validator, CatalogueRepository $repository): int
    {
        $customPath = $this->option('fichier');
        $this->info('--- Validation du catalogue d\'extractions ---');

        if ($customPath) {
            $fullPath = file_exists($customPath) ? $customPath : base_path($customPath);
            if (! file_exists($fullPath)) {
                $this->error("Fichier introuvable : {$customPath}");
                return self::FAILURE;
            }
            $content = file_get_contents($fullPath);
            $data = json_decode($content, true);
            if (! is_array($data)) {
                $this->error("Contenu JSON invalide dans le fichier : {$customPath}");
                return self::FAILURE;
            }
            $this->line("Fichier analysé : {$customPath}");
        } else {
            $data = $repository->loadRaw();
            $this->line("Fichier analysé : storage/app/private/extraction/queries.json");
        }

        $errors = $validator->validateCatalogue($data);

        if (! empty($errors)) {
            $this->error(sprintf('Le catalogue contient %d erreur(s) de validation :', count($errors)));
            foreach ($errors as $idx => $err) {
                $this->error(sprintf(' [%d] %s', $idx + 1, $err));
            }
            return self::FAILURE;
        }

        $this->info('Catalogue valide ! Aucune erreur détectée.');
        return self::SUCCESS;
    }
}
