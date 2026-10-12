<?php

namespace App\Console\Commands;

use App\Services\Extraction\ResultStore;
use Illuminate\Console\Command;

class PurgerResultats extends Command
{
    /**
     * Le nom et la signature de la commande.
     *
     * @var string
     */
    protected $signature = 'results:purger';

    /**
     * La description de la commande.
     *
     * @var string
     */
    protected $description = 'Purge les fichiers de résultats d\'extractions expirés.';

    public function handle(ResultStore $resultStore): int
    {
        $this->info('Purge des résultats expirés en cours...');
        $deleted = $resultStore->purgeExpired();
        $this->info(sprintf('%d fichier(s) de résultats expiré(s) purgé(s).', $deleted));

        return self::SUCCESS;
    }
}
