<?php

namespace App\Services\Extraction;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Service de lecture, filtrage et pagination des journaux d'exécution et de connexion.
 * Tolérant aux fichiers vides, absents ou corrompus.
 */
class LogReader
{
    public function __construct(
        protected ?CatalogueRepository $catalogueRepository = null,
        protected ?string $executionLogPath = null,
        protected ?string $authLogPath = null,
    ) {
        $this->catalogueRepository = $catalogueRepository ?? app(CatalogueRepository::class);
        $this->executionLogPath = $executionLogPath ?? config('extraction.log_file', 'extraction/execution_log.json');
        $this->authLogPath = $authLogPath ?? config('extraction.auth_log_file', 'extraction/auth_log.json');
    }

    /**
     * Lit et retourne les entrées brutes d'exécution normalisées.
     *
     * @return array<int, array>
     */
    public function readRawExecutionLogs(): array
    {
        return $this->readJsonFile($this->executionLogPath);
    }

    /**
     * Lit et retourne les entrées brutes d'authentification normalisées.
     *
     * @return array<int, array>
     */
    public function readRawAuthLogs(): array
    {
        return $this->readJsonFile($this->authLogPath);
    }

    /**
     * Récupère la liste filtrée, triée et paginée des exécutions.
     *
     * @param array $filters Filtres : utilisateur, extraction, recherche, mode, statut, date_debut, date_fin
     * @param int $page Numéro de page
     * @param int $perPage Nombre d'entrées par page (défaut: 50)
     */
    public function getExecutionLogs(array $filters = [], int $page = 1, int $perPage = 50): LengthAwarePaginator
    {
        $entries = $this->readRawExecutionLogs();
        $catalogueIndex = $this->getCatalogueIndex();

        $normalized = [];
        $totalRaw = count($entries);

        foreach ($entries as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $dateStr = (string) ($raw['date'] ?? $raw['timestamp'] ?? '');
            $code = (string) ($raw['code'] ?? $raw['extraction'] ?? '');
            $mode = strtolower((string) ($raw['mode'] ?? 'execution'));
            $statut = strtolower((string) ($raw['statut'] ?? 'succes'));
            if ($statut === 'reussi' || $statut === 'ok' || $statut === 'success') {
                $statut = 'succes';
            } elseif ($statut === 'erreur' || $statut === 'failed') {
                $statut = 'echec';
            }

            $catInfo = $catalogueIndex[$code] ?? null;
            $titre = $catInfo['titre'] ?? ($raw['titre'] ?? $code);
            $isActive = $catInfo ? (($catInfo['statut'] ?? '') === 'active') : false;

            // Numéro d'activité déterministe et lisible (ex: ACT-2841)
            $actId = $raw['id'] ?? ('ACT-' . str_pad((string) (1000 + ($totalRaw - $index)), 4, '0', STR_PAD_LEFT));

            $normalized[] = [
                'id'            => $actId,
                'date'          => $dateStr,
                'utilisateur'   => (string) ($raw['utilisateur'] ?? ''),
                'code'          => $code,
                'titre'         => $titre,
                'version'       => isset($raw['version']) ? (int) $raw['version'] : null,
                'mode'          => $mode,
                'parametres'    => is_array($raw['parametres'] ?? null) ? $raw['parametres'] : [],
                'statut'        => $statut,
                'nb_lignes'     => isset($raw['nb_lignes']) ? (int) $raw['nb_lignes'] : 0,
                'tronque'       => (bool) ($raw['tronque'] ?? false),
                'duree_ms'      => isset($raw['duree_ms']) ? (int) $raw['duree_ms'] : 0,
                'is_active'     => $isActive,
                'source'        => 'Amplitude · Lecture seule',
            ];
        }

        // Tri antéchronologique par date ISO 8601 (ordre lexicographique équivalent et ultra-rapide)
        usort($normalized, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        // Préparation des bornes de date pour comparaison rapide
        $debutStr = ! empty($filters['date_debut']) ? (string) $this->parseDate($filters['date_debut'])?->startOfDay()->toIso8601String() : null;
        $finStr = ! empty($filters['date_fin']) ? (string) $this->parseDate($filters['date_fin'])?->endOfDay()->toIso8601String() : null;

        // Application des filtres
        $filtered = array_filter($normalized, function ($item) use ($filters, $debutStr, $finStr) {
            // Filtre strict utilisateur
            if (! empty($filters['utilisateur'])) {
                if (strtolower($item['utilisateur']) !== strtolower((string) $filters['utilisateur'])) {
                    return false;
                }
            }

            // Filtre code extraction
            if (! empty($filters['extraction'])) {
                if (strcasecmp($item['code'], (string) $filters['extraction']) !== 0) {
                    return false;
                }
            }

            // Filtre mode / activité
            if (! empty($filters['mode']) && $filters['mode'] !== 'tous' && $filters['mode'] !== 'toutes') {
                if (strtolower($item['mode']) !== strtolower((string) $filters['mode'])) {
                    return false;
                }
            }

            // Filtre statut
            if (! empty($filters['statut']) && $filters['statut'] !== 'tous') {
                if (strtolower($item['statut']) !== strtolower((string) $filters['statut'])) {
                    return false;
                }
            }

            // Recherche globale (code, titre, utilisateur)
            if (! empty($filters['recherche'])) {
                $term = mb_strtolower(trim((string) $filters['recherche']));
                $haystack = mb_strtolower($item['code'] . ' ' . $item['titre'] . ' ' . $item['utilisateur']);
                if (! str_contains($haystack, $term)) {
                    return false;
                }
            }

            // Filtre date début
            if ($debutStr !== null && $item['date'] < $debutStr) {
                return false;
            }

            // Filtre date fin
            if ($finStr !== null && $item['date'] > $finStr) {
                return false;
            }

            return true;
        });

        $filteredCollection = collect(array_values($filtered));
        $total = $filteredCollection->count();
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $sliced = $filteredCollection->slice(($page - 1) * $perPage, $perPage)->values();

        // Ajout de Carbon uniquement sur les 50 éléments affichés sur la page
        $sliced = $sliced->map(function ($item) {
            $item['carbon_date'] = $this->parseDate($item['date']);
            return $item;
        });

        return new LengthAwarePaginator(
            $sliced,
            $total,
            $perPage,
            $page,
            [
                'path'  => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()?->query() ?? [],
            ]
        );
    }

    /**
     * Récupère la liste filtrée, triée et paginée des connexions.
     *
     * @param array $filters Filtres : recherche, identifiant, event, statut, date_debut, date_fin
     * @param int $page Numéro de page
     * @param int $perPage Nombre d'entrées par page (défaut: 50)
     */
    public function getAuthLogs(array $filters = [], int $page = 1, int $perPage = 50): LengthAwarePaginator
    {
        $entries = $this->readRawAuthLogs();
        $normalized = [];
        $totalRaw = count($entries);

        foreach ($entries as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $dateStr = (string) ($raw['timestamp'] ?? $raw['date'] ?? '');
            $event = strtolower((string) ($raw['event'] ?? 'login_success'));
            $identifiant = (string) ($raw['identifiant'] ?? 'inconnu');
            $ip = (string) ($raw['ip'] ?? '');
            $userAgent = (string) ($raw['user_agent'] ?? '');

            // Statut lisible
            $isSuccess = in_array($event, ['login_success', 'logout'], true);
            $statut = $isSuccess ? 'succes' : 'echec';

            // Libellé de l'événement en français
            $eventLabel = match ($event) {
                'login_success' => 'Connexion réussie',
                'login_failed'  => 'Échec de connexion',
                'lockout'       => 'Compte verrouillé',
                'logout'        => 'Déconnexion',
                default         => ucfirst($event),
            };

            $actId = 'LOG-' . str_pad((string) (1000 + ($totalRaw - $index)), 4, '0', STR_PAD_LEFT);

            $normalized[] = [
                'id'          => $actId,
                'date'        => $dateStr,
                'identifiant' => $identifiant,
                'event'       => $event,
                'event_label' => $eventLabel,
                'ip'          => $ip,
                'user_agent'  => $userAgent,
                'statut'      => $statut,
            ];
        }

        // Tri antéchronologique
        usort($normalized, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        $debutStr = ! empty($filters['date_debut']) ? (string) $this->parseDate($filters['date_debut'])?->startOfDay()->toIso8601String() : null;
        $finStr = ! empty($filters['date_fin']) ? (string) $this->parseDate($filters['date_fin'])?->endOfDay()->toIso8601String() : null;

        // Application des filtres
        $filtered = array_filter($normalized, function ($item) use ($filters, $debutStr, $finStr) {
            if (! empty($filters['identifiant'])) {
                if (strtolower($item['identifiant']) !== strtolower((string) $filters['identifiant'])) {
                    return false;
                }
            }

            if (! empty($filters['event']) && $filters['event'] !== 'tous') {
                if (strtolower($item['event']) !== strtolower((string) $filters['event'])) {
                    return false;
                }
            }

            if (! empty($filters['statut']) && $filters['statut'] !== 'tous') {
                if (strtolower($item['statut']) !== strtolower((string) $filters['statut'])) {
                    return false;
                }
            }

            if (! empty($filters['recherche'])) {
                $term = mb_strtolower(trim((string) $filters['recherche']));
                $haystack = mb_strtolower($item['identifiant'] . ' ' . $item['ip'] . ' ' . $item['event_label']);
                if (! str_contains($haystack, $term)) {
                    return false;
                }
            }

            if ($debutStr !== null && $item['date'] < $debutStr) {
                return false;
            }

            if ($finStr !== null && $item['date'] > $finStr) {
                return false;
            }

            return true;
        });

        $filteredCollection = collect(array_values($filtered));
        $total = $filteredCollection->count();
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $sliced = $filteredCollection->slice(($page - 1) * $perPage, $perPage)->values();

        $sliced = $sliced->map(function ($item) {
            $item['carbon_date'] = $this->parseDate($item['date']);
            return $item;
        });

        return new LengthAwarePaginator(
            $sliced,
            $total,
            $perPage,
            $page,
            [
                'path'  => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()?->query() ?? [],
            ]
        );
    }

    /**
     * Calcule les métriques clés (KPI) pour le tableau de bord d'administration des journaux.
     */
    public function getAdminKpis(): array
    {
        $rawExecs = $this->readRawExecutionLogs();
        $rawAuths = $this->readRawAuthLogs();

        $today = Carbon::today();
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $startOfMonth = Carbon::now()->startOfMonth();

        $activitesAujourdhui = 0;
        $activeUsersToday = [];

        $successLast30Days = 0;
        $totalLast30Days = 0;

        $exportsThisMonth = 0;

        foreach ($rawExecs as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $date = $this->parseDate($entry['date'] ?? $entry['timestamp'] ?? '');
            if (! $date) {
                continue;
            }

            // Aujourd'hui
            if ($date->greaterThanOrEqualTo($today)) {
                $activitesAujourdhui++;
                if (! empty($entry['utilisateur'])) {
                    $activeUsersToday[$entry['utilisateur']] = true;
                }
            }

            // 30 derniers jours
            if ($date->greaterThanOrEqualTo($thirtyDaysAgo)) {
                $totalLast30Days++;
                $statut = strtolower((string) ($entry['statut'] ?? 'succes'));
                if ($statut === 'succes' || $statut === 'reussi' || $statut === 'ok') {
                    $successLast30Days++;
                }
            }

            // Exports ce mois
            if ($date->greaterThanOrEqualTo($startOfMonth)) {
                $mode = strtolower((string) ($entry['mode'] ?? ''));
                if ($mode === 'export_csv') {
                    $exportsThisMonth++;
                }
            }
        }

        // Taux de succès
        $tauxSucces = $totalLast30Days > 0
            ? round(($successLast30Days / $totalLast30Days) * 100, 1)
            : 100.0;

        return [
            'activites_aujourdhui' => $activitesAujourdhui,
            'utilisateurs_actifs'  => count($activeUsersToday),
            'taux_succes'          => $tauxSucces,
            'exports_ce_mois'      => $exportsThisMonth,
        ];
    }

    /**
     * Calcule les métriques clés (KPI) pour l'historique personnel d'un utilisateur métier.
     */
    public function getUserKpis(string $utilisateur): array
    {
        $rawExecs = $this->readRawExecutionLogs();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $execsThisMonth = 0;
        $execsLastMonth = 0;
        $successLast30Days = 0;
        $totalLast30Days = 0;

        $lastExportDate = null;
        $lastExportCode = null;

        foreach ($rawExecs as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (strtolower((string) ($entry['utilisateur'] ?? '')) !== strtolower($utilisateur)) {
                continue;
            }

            $date = $this->parseDate($entry['date'] ?? $entry['timestamp'] ?? '');
            if (! $date) {
                continue;
            }

            $mode = strtolower((string) ($entry['mode'] ?? ''));

            if ($date->greaterThanOrEqualTo($startOfMonth)) {
                if ($mode === 'execution' || $mode === 'test' || $mode === 'affichage') {
                    $execsThisMonth++;
                }
            } elseif ($date->between($startOfLastMonth, $endOfLastMonth)) {
                if ($mode === 'execution' || $mode === 'test' || $mode === 'affichage') {
                    $execsLastMonth++;
                }
            }

            if ($date->greaterThanOrEqualTo($thirtyDaysAgo)) {
                $totalLast30Days++;
                $statut = strtolower((string) ($entry['statut'] ?? 'succes'));
                if ($statut === 'succes' || $statut === 'reussi' || $statut === 'ok') {
                    $successLast30Days++;
                }
            }

            if ($mode === 'export_csv') {
                if ($lastExportDate === null || $date->gt($lastExportDate)) {
                    $lastExportDate = $date;
                    $lastExportCode = $entry['code'] ?? $entry['extraction'] ?? '';
                }
            }
        }

        $tauxSucces = $totalLast30Days > 0
            ? round(($successLast30Days / $totalLast30Days) * 100, 1)
            : 100.0;

        $diffExecs = $execsThisMonth - $execsLastMonth;
        $diffSign = $diffExecs >= 0 ? '+' : '';
        $nomMoisPrecedent = Carbon::now()->subMonth()->translatedFormat('F');

        $dernierExportLabel = 'Aucun';
        $dernierExportSub = '–';
        if ($lastExportDate) {
            $dernierExportLabel = $lastExportDate->format('H:i');
            $dernierExportSub = ($lastExportDate->isToday() ? "Aujourd'hui" : $lastExportDate->format('d/m/Y'))
                . ($lastExportCode ? " · {$lastExportCode}" : '');
        }

        return [
            'executions_ce_mois'       => $execsThisMonth,
            'comparaison_mois_dernier' => "{$diffSign}{$diffExecs} par rapport à {$nomMoisPrecedent}",
            'taux_succes'              => $tauxSucces,
            'dernier_export_heure'     => $dernierExportLabel,
            'dernier_export_detail'    => $dernierExportSub,
        ];
    }

    /**
     * Lit un fichier JSON de manière sécurisée et tolérante aux pannes.
     *
     * @return array<int, array>
     */
    protected function readJsonFile(string $relativePath): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($relativePath)) {
            return [];
        }

        try {
            $content = $disk->get($relativePath);
            $trimmed = trim($content);
            if ($trimmed === '') {
                return [];
            }

            // Si c'est un tableau JSON standard [ ... ]
            if ($trimmed[0] === '[') {
                $decoded = json_decode($trimmed, true);
                if (! is_array($decoded)) {
                    return [];
                }

                // Vérifie que les éléments sont des tableaux associatifs
                $valid = [];
                foreach ($decoded as $item) {
                    if (is_array($item)) {
                        $valid[] = $item;
                    }
                }

                return $valid;
            }

            // Support transparent du format JSON Lines (NDJSON)
            $lines = explode("\n", $trimmed);
            $valid = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $valid[] = $decoded;
                }
            }

            return $valid;
        } catch (\Throwable $e) {
            // Tolérance totale aux pannes de lecture / corruption
            return [];
        }
    }

    /**
     * Récupère un index [code => extraction] depuis le catalogue.
     */
    protected function getCatalogueIndex(): array
    {
        if (! $this->catalogueRepository) {
            return [];
        }

        try {
            $all = $this->catalogueRepository->all();
            $index = [];
            foreach ($all as $item) {
                if (! empty($item['code'])) {
                    $index[$item['code']] = $item;
                }
            }
            return $index;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Parse une date en objet Carbon de manière sûre.
     */
    protected function parseDate(?string $dateStr): ?Carbon
    {
        if (! $dateStr) {
            return null;
        }

        try {
            return Carbon::parse($dateStr);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
