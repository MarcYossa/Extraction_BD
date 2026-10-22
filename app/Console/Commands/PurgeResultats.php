<?php

namespace App\Console\Commands;

use App\Services\Extraction\ResultStore;
use Illuminate\Console\Command;

class PurgeResultats extends Command
{
    /**
     * Le nom et la signature de la commande Artisan.
     *
     * @var string
     */
    protected $signature = 'app:purge-resultats';

    /**
     * La description de la commande.
     *
     * @var string
     */
    protected $description = 'Supprime les fichiers temporaires de résultats d\'extraction expirés (storage/app/private/results).';

    /**
     * Exécute la commande.
     */
    public function handle(ResultStore $resultStore): int
    {
        $this->info('Nettoyage des résultats d\'extraction expirés...');

        $deleted = $resultStore->purgeExpired();

        $this->info(sprintf('%d fichier(s) de résultats expiré(s) supprimé(s).', $deleted));

        return self::SUCCESS;
    }
}
