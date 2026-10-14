@extends('layouts.app')

@section('title', "Résultats — {$extraction['titre']} — Espace Métier")

@section('content')

{{-- Lien retour aux paramètres --}}
<div style="margin-bottom: var(--space-4);">
    <a href="{{ route('extractions.show', ['code' => $extraction['code'], 'jeton' => $jeton]) }}" class="back-link" id="link-retour-parametres">
        ‹ Retour aux paramètres
    </a>
</div>

{{-- En-tête M04 (04-resultats.png) --}}
<div style="display: flex; justify-content: space-between; align-items: flex-start; gap: var(--space-6); margin-bottom: var(--space-6); flex-wrap: wrap;">
    <div>
        <span style="font-size: var(--font-xs); font-weight: 700; letter-spacing: 0.1em; color: var(--color-text-muted); text-transform: uppercase; display: block; margin-bottom: var(--space-1);">
            EXTRACTION EXÉCUTÉE
        </span>
        <h1 style="font-size: var(--font-2xl); font-weight: 600; color: var(--color-text-heading); font-family: var(--font-family-display); margin: 0 0 var(--space-2) 0; line-height: 1.2;">
            {{ $extraction['titre'] }}
        </h1>
        <div style="display: inline-flex; align-items: center; gap: var(--space-2); color: var(--color-success); font-size: var(--font-sm); font-weight: 500;">
            <x-icon name="check" size="16" />
            <span>Exécution terminée avec succès</span>
        </div>
    </div>

    <div>
        <a
            href="{{ route('extractions.csv', ['code' => $extraction['code'], 'jeton' => $jeton]) }}"
            class="btn btn--primary"
            id="btn-exporter-csv"
            style="gap: var(--space-2);"
        >
            <x-icon name="download" size="16" />
            <span>Exporter en CSV</span>
        </a>
    </div>
</div>

{{-- Bandeau des paramètres utilisés --}}
<div style="margin-bottom: var(--space-6);">
    <x-params-bandeau :items="$resultat['parametres_affiches'] ?? []" />
</div>

{{-- Bandeau d'avertissement si le plafond a été atteint (D21) --}}
@if (! empty($resultat['tronque']))
    <div class="alert alert--warning" style="margin-bottom: var(--space-6);" role="alert">
        <div style="display: flex; align-items: center; gap: var(--space-2);">
            <x-icon name="alert-triangle" size="18" />
            <span><strong>Résultat limité :</strong> affinez vos critères pour obtenir un résultat plus précis.</span>
        </div>
    </div>
@endif

{{-- Carte des résultats avec tableau paginé --}}
<div class="card" style="padding: var(--space-6); background: var(--color-bg-card);">
    <div style="margin-bottom: var(--space-4);">
        <h2 style="font-size: var(--font-lg); font-weight: 600; color: var(--color-text-heading); margin: 0 0 var(--space-1) 0; font-family: var(--font-family-display);">
            Résultats
        </h2>
        <div style="font-size: var(--font-xs); color: var(--color-text-muted);" id="compteur-lignes">
            @php
                $nbLignes = (int) ($resultat['nb_lignes'] ?? count($resultat['lignes'] ?? []));
            @endphp
            @if (! empty($resultat['tronque']))
                {{ number_format($nbLignes, 0, ',', ' ') }} lignes (plafond atteint)
            @else
                {{ number_format($nbLignes, 0, ',', ' ') }} {{ $nbLignes > 1 ? 'lignes retournées' : 'ligne retournée' }}
            @endif
        </div>
    </div>

    {{-- Tableau de résultats paginé Alpine (50 lignes/page, D8) --}}
    <x-results-table
        :colonnes="$resultat['colonnes'] ?? []"
        :lignes="$resultat['lignes'] ?? []"
        :page-size="50"
        empty-message="Aucun résultat."
    />
</div>

@endsection
