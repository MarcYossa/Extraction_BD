<?php

return [
    // Plafond maximal de lignes retournées par extraction pour protéger la mémoire
    'max_rows'        => (int) env('EXTRACTION_MAX_ROWS', 10000),

    // Délai maximal d'exécution applicatif d'une requête Oracle en secondes.
    // Note d'exploitation DOSI pour la production :
    // Pour garantir l'interruption des requêtes bloquantes au niveau réseau,
    // configurer également SQLNET.RECV_TIMEOUT = 60 dans le fichier sqlnet.ora du client Oracle.
    'timeout_seconds' => (int) env('EXTRACTION_TIMEOUT_SECONDS', 60),

    'queries_file'             => 'extraction/queries.json',
    'log_file'                 => 'extraction/execution_log.json',
    'auth_log_file'            => 'extraction/auth_log.json',
    'results_dir'              => 'results',
    'results_lifetime_minutes' => 15,
];