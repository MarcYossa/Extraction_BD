<?php

return [
    'max_rows'        => (int) env('EXTRACTION_MAX_ROWS', 10000),
    'timeout_seconds' => (int) env('EXTRACTION_TIMEOUT_SECONDS', 60),
    'queries_file'    => 'extraction/queries.json',
    'log_file'        => 'extraction/execution_log.json',
];