@extends('layouts.app')

@section('title', 'Mon historique — Espace Métier')

@section('content')

{{-- ── En-tête de page (maquette 5) ───────────────────────────── --}}
<x-page-header
    overtitle="MES EXTRACTIONS"
    title="Retrouver mes extractions"
    subtitle="Consultez vos dernières exécutions et relancez-les avec les mêmes paramètres."
/>

{{-- ── Cartes d'indicateurs KPI (3 colonnes) ──────────────────── --}}
<div class="kpi-grid" id="kpis-historique">
    <div class="card kpi-card" id="kpi-executions-ce-mois">
        <span class="kpi-card__overtitle">EXÉCUTIONS CE MOIS</span>
        <div class="kpi-card__value">{{ $kpis['executions_ce_mois'] }}</div>
        <div class="kpi-card__subtitle">{{ $kpis['comparaison_mois_dernier'] }}</div>
    </div>

    <div class="card kpi-card" id="kpi-taux-succes-metier">
        <span class="kpi-card__overtitle">TAUX DE SUCCÈS</span>
        <div class="kpi-card__value">{{ number_format($kpis['taux_succes'], 1, ',', ' ') }} %</div>
        <div class="kpi-card__subtitle">Sur les 30 derniers jours</div>
    </div>

    <div class="card kpi-card" id="kpi-dernier-export">
        <span class="kpi-card__overtitle">DERNIER EXPORT</span>
        <div class="kpi-card__value">{{ $kpis['dernier_export_heure'] }}</div>
        <div class="kpi-card__subtitle">{{ $kpis['dernier_export_detail'] }}</div>
    </div>
</div>

{{-- ── Barre de recherche et de filtres ───────────────────────── --}}
<form method="GET" action="{{ route('mes-extractions') }}" class="search-filter-bar" id="form-filtres-historique">
    <div class="search-input-wrapper">
        <x-icon name="search" size="16" class="search-icon" />
        <input
            type="text"
            name="q"
            id="input-recherche-historique"
            class="input search-input"
            placeholder="Rechercher une extraction"
            value="{{ $filters['recherche'] }}"
            autocomplete="off"
        />
    </div>

    <select name="mode" id="select-activite-metier" class="input select-statut" onchange="this.form.submit()">
        <option value="tous" @selected($filters['mode'] === 'tous' || $filters['mode'] === '')>Toutes les activités</option>
        <option value="execution" @selected($filters['mode'] === 'execution')>Exécution</option>
        <option value="export_csv" @selected($filters['mode'] === 'export_csv')>Export CSV</option>
    </select>

    <select name="statut" id="select-statut-metier" class="input select-statut" onchange="this.form.submit()">
        <option value="tous" @selected($filters['statut'] === 'tous' || $filters['statut'] === '')>Tous les statuts</option>
        <option value="succes" @selected($filters['statut'] === 'succes')>Succès</option>
        <option value="echec" @selected($filters['statut'] === 'echec')>Échec</option>
    </select>

    <button type="submit" class="btn btn--outline btn--sm" id="btn-filtrer-metier">
        Filtrer
    </button>

    @if ($filters['recherche'] !== '' || ($filters['mode'] !== '' && $filters['mode'] !== 'tous') || ($filters['statut'] !== '' && $filters['statut'] !== 'tous'))
        <a href="{{ route('mes-extractions') }}" class="btn btn--ghost btn--sm" id="btn-effacer-filtres-metier">
            <x-icon name="x" size="14" />
            Effacer
        </a>
    @endif
</form>

{{-- ── Carte principale du tableau de bord d'historique ───────── --}}
<div class="card logs-card" id="card-historique-metier">

    <div class="logs-card__header">
        <div>
            <div class="logs-card__title" id="compteur-historique">
                {{ $logs->total() }} {{ $logs->total() > 1 ? 'activités' : 'activité' }}
            </div>
            <div class="logs-card__subtitle">
                Vos 30 derniers jours d'activité
            </div>
        </div>

        <a href="{{ route('mes-extractions.export', request()->query()) }}"
           class="btn btn--outline btn--sm"
           id="btn-exporter-historique">
            <x-icon name="download" size="14" />
            Exporter l'historique
        </a>
    </div>

    @if ($logs->total() === 0)
        <div class="empty-state" id="empty-historique">
            <x-icon name="inbox" size="40" style="color:var(--color-text-muted)" />
            <p class="empty-state__title">Aucune extraction trouvée</p>
            <p class="empty-state__desc">
                @if ($filters['recherche'] !== '' || $filters['mode'] !== 'tous' || $filters['statut'] !== 'tous')
                    Aucune activité ne correspond à vos filtres.
                @else
                    Vous n'avez pas encore exécuté d'extraction. Rendez-vous dans le catalogue pour lancer votre première requête.
                @endif
            </p>
            @if ($filters['recherche'] === '' && $filters['mode'] === 'tous' && $filters['statut'] === 'tous')
                <a href="{{ route('catalogue') }}" class="btn btn--primary" id="btn-vers-catalogue">
                    Parcourir le catalogue
                </a>
            @endif
        </div>
    @else

        <div class="table-container">
            <table class="data-table" id="table-historique">
                <thead>
                    <tr>
                        <th>DATE ET HEURE</th>
                        <th>ACTIVITÉ</th>
                        <th>EXTRACTION</th>
                        <th>STATUT</th>
                        <th style="text-align: right;">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs->items() as $item)
                        @php
                            $carbonDate = $item['carbon_date'] ?? null;
                            $dateFormatted = $carbonDate ? $carbonDate->translatedFormat('d M. Y') : '–';
                            $timeFormatted = $carbonDate ? $carbonDate->format('H:i') : '–';

                            $modeLabel = match($item['mode']) {
                                'execution'  => 'Exécution',
                                'export_csv' => 'Export CSV',
                                default      => ucfirst($item['mode']),
                            };

                            $dureeSec = $item['duree_ms'] > 0 ? number_format($item['duree_ms'] / 1000, 1, ',', ' ') . ' s' : '–';
                        @endphp
                        <tr id="historique-row-{{ $item['id'] }}">
                            <td>
                                <div class="log-date-cell">
                                    <span class="log-date">{{ $dateFormatted }}</span>
                                    <span class="log-time-id">{{ $timeFormatted }} · {{ $item['id'] }}</span>
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
                                    </span>
                                </div>
                            </td>
                            <td style="text-align: right;">
                                @if ($item['is_active'])
                                    <a href="{{ route('extractions.show', array_filter(['code' => $item['code'], 'params' => $item['parametres']])) }}"
                                       class="btn btn--outline btn--sm"
                                       id="btn-relancer-{{ $item['id'] }}"
                                       title="Relancer avec les paramètres de cette exécution">
                                        <x-icon name="play" size="14" />
                                        Relancer
                                    </a>
                                @else
                                    <button
                                        type="button"
                                        class="btn btn--outline btn--sm"
                                        disabled
                                        aria-disabled="true"
                                        id="btn-relancer-disabled-{{ $item['id'] }}"
                                        title="Cette extraction n'est plus active dans le catalogue"
                                        style="opacity: 0.5; cursor: not-allowed;"
                                    >
                                        <x-icon name="play" size="14" />
                                        Relancer
                                    </button>
                                    <span class="text-xs text-muted block mt-1" style="display:block; font-size:11px; color:var(--text-muted);">
                                        Extraction inactive
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- ── Pagination ───────────────────────────────────────────── --}}
        <div class="table-pagination">
            <div class="table-pagination__info">
                Affichage de {{ $logs->firstItem() ?? 0 }} à {{ $logs->lastItem() ?? 0 }} sur {{ $logs->total() }} activités
            </div>
            <div class="table-pagination__links">
                {{ $logs->appends(request()->query())->links() }}
            </div>
        </div>

    @endif

</div>

@endsection
