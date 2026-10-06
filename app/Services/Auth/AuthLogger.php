<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Storage;

/**
 * Journalise les événements d'authentification (connexion réussie, échec, verrouillage, déconnexion)
 * dans le fichier storage/app/private/extraction/auth_log.json avec verrou de fichier.
 */
class AuthLogger
{
    /**
     * Chemin relatif dans le disque local storage/app/private.
     */
    protected string $relativePath = 'extraction/auth_log.json';

    /**
     * Enregistre une entrée dans le journal des connexions.
     *
     * @param string $event       'login_success' | 'login_failed' | 'lockout' | 'logout'
     * @param string $identifiant Identifiant saisi (ou 'inconnu')
     * @param string $ip          Adresse IP du client
     * @param string $userAgent   User Agent du navigateur (tronqué si nécessaire)
     */
    public function log(string $event, string $identifiant, string $ip, string $userAgent): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($this->relativePath));
        $fullPath = $disk->path($this->relativePath);

        $entry = [
            'event'       => $event,
            'identifiant' => $identifiant,
            'ip'          => $ip,
            'timestamp'   => now()->toIso8601String(),
            'user_agent'  => mb_substr($userAgent, 0, 150),
        ];

        $handle = fopen($fullPath, 'c+');
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

    public function logSuccess(string $identifiant, string $ip, string $userAgent): void
    {
        $this->log('login_success', $identifiant, $ip, $userAgent);
    }

    public function logFailed(string $identifiant, string $ip, string $userAgent): void
    {
        $this->log('login_failed', $identifiant, $ip, $userAgent);
    }

    public function logLockout(string $identifiant, string $ip, string $userAgent): void
    {
        $this->log('lockout', $identifiant, $ip, $userAgent);
    }

    public function logLogout(string $identifiant, string $ip, string $userAgent): void
    {
        $this->log('logout', $identifiant, $ip, $userAgent);
    }
}
