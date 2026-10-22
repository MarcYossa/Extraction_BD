@extends('layouts.app')

@section('title', "Journal d'activité — Administration")

@section('content')

{{-- ── En-tête de page ────────────────────────────────────────── --}}
<x-page-header
    overtitle="TRAÇABILITÉ"
    title="Suivre l'activité de la plateforme"
    subtitle="Consultez les exécutions, tests, exports, publications et connexions."
/>

{{-- ── Cartes d'indicateurs KPI (3 colonnes) ──────────────────── --}}
<div class="kpi-grid" id="kpis-journal">
    <div class="card kpi-card" id="kpi-activites-aujourdhui">
        <span class="kpi-card__overtitle">ACTIVITÉS AUJOURD'HUI</span>
        <div class="kpi-card__value">{{ $kpis['activites_aujourdhui'] }}</div>
        <div class="kpi-card__subtitle">
            {{ $kpis['utilisateurs_actifs'] }} {{ $kpis['utilisateurs_actifs'] > 1 ? 'utilisateurs actifs' : 'utilisateur actif' }}
        </div>
    </div>

    <div class="card kpi-card" id="kpi-taux-succes">
        <span class="kpi-card__overtitle">TAUX DE SUCCÈS</span>
        <div class="kpi-card__value">{{ number_format($kpis['taux_succes'], 1, ',', ' ') }} %</div>
        <div class="kpi-card__subtitle">Sur les 30 derniers jours</div>
    </div>

    <div class="card kpi-card" id="kpi-exports-generes">
        <span class="kpi-card__overtitle">EXPORTS GÉNÉRÉS</span>
        <div class="kpi-card__value">{{ $kpis['exports_ce_mois'] }}</div>
        <div class="kpi-card__subtitle">Ce mois-ci</div>
    </div>
</div>

<div x-data="{ selectedActivity: null }" class="logs-container">

    {{-- ── Onglets et barre de recherche ────────────────────────── --}}
    <div class="logs-navigation-bar">
        <div class="tabs-nav" id="tabs-journal">
            <a href="{{ route('admin.logs.index', array_merge(request()->query(), ['onglet' => 'executions', 'page' => 1])) }}"
               class="tab-link {{ $onglet === 'executions' ? 'tab-link--active' : '' }}"
               id="tab-executions">
                <x-icon name="database" size="16" />
                Exécutions & Activités
            </a>
            <a href="{{ route('admin.logs.index', array_merge(request()->query(), ['onglet' => 'connexions', 'page' => 1])) }}"
               class="tab-link {{ $onglet === 'connexions' ? 'tab-link--active' : '' }}"
               id="tab-connexions">
                <x-icon name="user" size="16" />
                Connexions
            </a>
        </div>
    </div>

    {{-- ── Formulaire de filtres ─────────────────────────────────── --}}
    <form method="GET" action="{{ route('admin.logs.index') }}" class="search-filter-bar" id="form-filtres-logs">
        <input type="hidden" name="onglet" value="{{ $onglet }}" />

        <div class="search-input-wrapper">
            <x-icon name="search" size="16" class="search-icon" />
            <input
                type="text"
                name="q"
                id="input-recherche-logs"
                class="input search-input"
                placeholder="{{ $onglet === 'connexions' ? 'Rechercher par identifiant ou adresse IP' : 'Rechercher par extraction ou utilisateur' }}"
                value="{{ $filters['recherche'] }}"
                autocomplete="off"
            />
        </div>

        @if ($onglet === 'executions')
            <select name="mode" id="select-activite" class="input select-statut" onchange="this.form.submit()">
                <option value="tous" @selected($filters['mode'] === 'tous' || $filters['mode'] === '')>Toutes les activités</option>
                <option value="execution" @selected($filters['mode'] === 'execution')>Exécution</option>
                <option value="export_csv" @selected($filters['mode'] === 'export_csv')>Export CSV</option>
                <option value="publication" @selected($filters['mode'] === 'publication')>Publication</option>
                <option value="test" @selected($filters['mode'] === 'test')>Test</option>
                <option value="desactivation" @selected($filters['mode'] === 'desactivation')>Désactivation</option>
                <option value="reactivation" @selected($filters['mode'] === 'reactivation')>Réactivation</option>
            </select>
        @else
            <select name="mode" id="select-event" class="input select-statut" onchange="this.form.submit()">
                <option value="tous" @selected($filters['mode'] === 'tous' || $filters['mode'] === '')>Tous les événements</option>
                <option value="login_success" @selected($filters['mode'] === 'login_success')>Connexion réussie</option>
                <option value="login_failed" @selected($filters['mode'] === 'login_failed')>Échec de connexion</option>
                <option value="lockout" @selected($filters['mode'] === 'lockout')>Compte verrouillé</option>
                <option value="logout" @selected($filters['mode'] === 'logout')>Déconnexion</option>
            </select>
        @endif

        <select name="statut" id="select-statut" class="input select-statut" onchange="this.form.submit()">
            <option value="tous" @selected($filters['statut'] === 'tous' || $filters['statut'] === '')>Tous les statuts</option>
            <option value="succes" @selected($filters['statut'] === 'succes')>Succès</option>
            <option value="echec" @selected($filters['statut'] === 'echec')>Échec</option>
        </select>

        <button type="submit" class="btn btn--outline btn--sm" id="btn-filtrer">
            Filtrer
        </button>

        @if ($filters['recherche'] !== '' || ($filters['mode'] !== '' && $filters['mode'] !== 'tous') || ($filters['statut'] !== '' && $filters['statut'] !== 'tous'))
            <a href="{{ route('admin.logs.index', ['onglet' => $onglet]) }}" class="btn btn--ghost btn--sm" id="btn-reinitialiser">
                <x-icon name="x" size="14" />
                Effacer
            </a>
        @endif
    </form>

    {{-- ── Carte principale du tableau ──────────────────────────── --}}
    <div class="card logs-card" id="card-tableau-logs">

        <div class="logs-card__header">
            <div>
                <div class="logs-card__title" id="compteur-logs">
                    {{ $logs->total() }} {{ $logs->total() > 1 ? 'activités' : 'activité' }}
                </div>
                <div class="logs-card__subtitle">
                    Journal mis à jour à l'instant
                </div>
            </div>

            <a href="{{ route('admin.logs.export', request()->query()) }}"
               class="btn btn--outline btn--sm"
               id="btn-exporter-journal">
                <x-icon name="download" size="14" />
                Exporter le journal
            </a>
        </div>

        @if ($logs->total() === 0)
            <div class="empty-state" id="empty-logs">
                <x-icon name="inbox" size="40" style="color:var(--color-text-muted)" />
                <p class="empty-state__title">Aucune activité enregistrée</p>
                <p class="empty-state__desc">
                    @if ($filters['recherche'] !== '' || $filters['mode'] !== 'tous' || $filters['statut'] !== 'tous')
                        Aucun enregistrement ne correspond à vos filtres actuels.
                    @else
                        Le journal ne contient aucun événement pour le moment.
                    @endif
                </p>
            </div>
        @else

            <div class="table-container">
                @if ($onglet === 'executions')
                    {{-- ── Tableau des exécutions ── --}}
                    <table class="data-table" id="table-executions">
                        <thead>
                            <tr>
                                <th>DATE ET HEURE</th>
                                <th>UTILISATEUR</th>
                                <th>ACTIVITÉ</th>
                                <th>EXTRACTION</th>
                                <th>STATUT</th>
                                <th style="text-align: right;">DÉTAILS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs->items() as $item)
                                @php
                                    $carbonDate = $item['carbon_date'] ?? null;
                                    $dateFormatted = $carbonDate ? $carbonDate->translatedFormat('d M. Y') : '–';
                                    $timeFormatted = $carbonDate ? $carbonDate->format('H:i') : '–';
                                    $userInitial = mb_strtoupper(mb_substr($item['utilisateur'] ?: '?', 0, 2));

                                    $modeLabel = match($item['mode']) {
                                        'execution'    => 'Exécution',
                                        'export_csv'   => 'Export CSV',
                                        'publication'  => 'Publication',
                                        'test'         => 'Test',
                                        'desactivation'=> 'Désactivation',
                                        'reactivation' => 'Réactivation',
                                        default        => ucfirst($item['mode']),
                                    };

                                    $dureeSec = $item['duree_ms'] > 0 ? number_format($item['duree_ms'] / 1000, 1, ',', ' ') . ' s' : '–';
                                @endphp
                                <tr id="log-row-{{ $item['id'] }}">
                                    <td>
                                        <div class="log-date-cell">
                                            <span class="log-date">{{ $dateFormatted }}</span>
                                            <span class="log-time-id">{{ $timeFormatted }} · {{ $item['id'] }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="log-user-cell">
                                            <x-avatar :initials="$userInitial" size="sm" />
                                            <span class="log-user-name">{{ $item['utilisateur'] }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="log-activity-badge">
                                            <span class="log-activity-dot {{ $item['mode'] === 'export_csv' ? 'log-activity-dot--export' : '' }}"></span>
                                            {{ $modeLabel }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="log-extraction-cell">
                                            @if ($item['code'])
                                                <x-chip-code :code="$item['code']" />
                                                <span class="log-extraction-title">{{ $item['titre'] }}</span>
                                            @else
                                                <span class="text-muted">–</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="log-status-cell">
                                            @if ($item['statut'] === 'succes')
                                                <span class="badge-statut badge-statut--active">
                                                    ● Succès
                                                </span>
                                            @else
                                                <span class="badge-statut badge-statut--inactive" style="background:#fee2e2; color:#b91c1c;">
                                                    ● Échec
                                                </span>
                                            @endif
                                            <span class="log-duration">
                                                {{ $dureeSec }}
                                                @if ($item['tronque'])
                                                    <span class="text-muted" title="Plafond atteint">(tronqué)</span>
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <button
                                            type="button"
                                            class="btn-consulter-details"
                                            @click="selectedActivity = {{ json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }}"
                                            id="btn-consulter-{{ $item['id'] }}"
                                        >
                                            Consulter
                                            <x-icon name="chevron-right" size="14" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                @else
                    {{-- ── Tableau des connexions ── --}}
                    <table class="data-table" id="table-connexions">
                        <thead>
                            <tr>
                                <th>DATE ET HEURE</th>
                                <th>IDENTIFIANT</th>
                                <th>ÉVÉNEMENT</th>
                                <th>STATUT</th>
                                <th>ADRESSE IP</th>
                                <th>NAVIGATEUR</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs->items() as $item)
                                @php
                                    $carbonDate = $item['carbon_date'] ?? null;
                                    $dateFormatted = $carbonDate ? $carbonDate->translatedFormat('d M. Y H:i:s') : $item['date'];
                                    $userInitial = mb_strtoupper(mb_substr($item['identifiant'] ?: '?', 0, 2));
                                @endphp
                                <tr id="auth-row-{{ $item['id'] }}">
                                    <td>
                                        <div class="log-date-cell">
                                            <span class="log-date">{{ $dateFormatted }}</span>
                                            <span class="log-time-id">{{ $item['id'] }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="log-user-cell">
                                            <x-avatar :initials="$userInitial" size="sm" />
                                            <span class="log-user-name">{{ $item['identifiant'] }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="log-activity-badge">
                                            <span class="log-activity-dot {{ $item['statut'] === 'succes' ? '' : 'log-activity-dot--error' }}"></span>
                                            {{ $item['event_label'] }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($item['statut'] === 'succes')
                                            <span class="badge-statut badge-statut--active">
                                                ● Succès
                                            </span>
                                        @else
                                            <span class="badge-statut badge-statut--inactive" style="background:#fee2e2; color:#b91c1c;">
                                                ● Échec
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="font-mono text-sm">{{ $item['ip'] ?: '–' }}</span>
                                    </td>
                                    <td>
                                        <span class="text-sm text-muted" title="{{ $item['user_agent'] }}">
                                            {{ Str::limit($item['user_agent'] ?: '–', 45) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- ── Pagination ───────────────────────────────────────── --}}
            <div class="table-pagination">
                <div class="table-pagination__info">
                    Affichage de {{ $logs->firstItem() ?? 0 }} à {{ $logs->lastItem() ?? 0 }} sur {{ $logs->total() }}
                </div>
                <div class="table-pagination__links">
                    {{ $logs->appends(request()->query())->links() }}
                </div>
            </div>

        @endif

        {{-- ── Volet de détail d'une activité sélectionnée (maquette 9.1) ── --}}
        <div x-show="selectedActivity !== null"
             x-cloak
             x-transition
             class="activity-details-panel"
             id="panneau-details-activite">

            <div class="activity-details-panel__top">
                <div class="activity-details-panel__left">
                    <span class="activity-details-panel__overtitle">DÉTAIL DE L'ACTIVITÉ</span>
                    <h3 class="activity-details-panel__title" x-text="selectedActivity ? selectedActivity.titre : ''"></h3>
                    <div class="activity-details-panel__meta">
                        <span>Date : <strong x-text="selectedActivity ? selectedActivity.date : ''"></strong></span>
                        <template x-if="selectedActivity && Object.keys(selectedActivity.parametres || {}).length > 0">
                            <span>
                                · Paramètres :
                                <template x-for="(val, key) in selectedActivity.parametres" :key="key">
                                    <span class="param-tag" x-text="key + ' : ' + val"></span>
                                </template>
                            </span>
                        </template>
                        <template x-if="selectedActivity && Object.keys(selectedActivity.parametres || {}).length === 0">
                            <span>· Aucun paramètre requis</span>
                        </template>
                    </div>
                </div>

                <button type="button" class="btn btn--ghost btn--sm" @click="selectedActivity = null" id="btn-fermer-details">
                    <x-icon name="x" size="16" />
                    Fermer
                </button>
            </div>

            <div class="activity-details-grid">
                <div class="activity-detail-item">
                    <span class="activity-detail-item__label">IDENTIFIANT</span>
                    <span class="activity-detail-item__value" x-text="selectedActivity ? selectedActivity.id : ''"></span>
                </div>
                <div class="activity-detail-item">
                    <span class="activity-detail-item__label">VOLUME</span>
                    <span class="activity-detail-item__value">
                        <span x-text="selectedActivity ? (selectedActivity.nb_lignes + ' lignes') : '0 ligne'"></span>
                        <template x-if="selectedActivity && selectedActivity.tronque">
                            <span class="text-muted text-xs">(tronqué)</span>
                        </template>
                    </span>
                </div>
                <div class="activity-detail-item">
                    <span class="activity-detail-item__label">DURÉE</span>
                    <span class="activity-detail-item__value" x-text="selectedActivity ? (selectedActivity.duree_ms > 0 ? (selectedActivity.duree_ms / 1000).toFixed(1) + ' s' : '–') : ''"></span>
                </div>
                <div class="activity-detail-item">
                    <span class="activity-detail-item__label">SOURCE</span>
                    <span class="activity-detail-item__value" x-text="selectedActivity ? selectedActivity.source : 'Amplitude · Lecture seule'"></span>
                </div>
            </div>
        </div>

    </div>

</div>

@endsection
