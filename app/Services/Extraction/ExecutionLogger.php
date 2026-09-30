<?php

namespace App\Services\Extraction;

use Illuminate\Support\Facades\Storage;

/**
 * Journal des executions dans un fichier JSON (storage/app/private/extraction/execution_log.json).
 * Un verrou de fichier evite la corruption si deux executions ecrivent en meme temps.
 */
class ExecutionLogger
{
    public function record(array $entry): void
    {
        $disk = Storage::disk('local');
        $relative = config('extraction.log_file');
        $disk->makeDirectory(dirname($relative));
        $path = $disk->path($relative);

        $entry = ['date' => now()->toIso8601String()] + $entry;

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            flock($handle, LOCK_EX);
            $content = stream_get_contents($handle);
            $log = json_decode($content === false || $content === '' ? '[]' : $content, true);
            if (!is_array($log)) {
                $log = [];
            }
            $log[] = $entry;

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }
}
