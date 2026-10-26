@extends('layouts.app')

@section('title', "Résultat du test {$extraction['code']} — Administration")

@section('content')

{{-- Lien retour aux paramètres d'exécution avec préservation des valeurs saisies --}}
<div style="margin-bottom: var(--space-4);">
    <a href="{{ route('admin.extractions.test', ['code' => $extraction['code'], 'jeton' => $jeton]) }}" class="back-link" id="link-retour-parametres">
        ‹ Retour aux paramètres
    </a>
</div>

{{-- En-tête S07 (08-test-resultat.png) --}}
<div style="margin-bottom: var(--space-4);">
    <span style="font-size: var(--font-xs); font-weight: 700; letter-spacing: 0.1em; color: #476a82; text-transform: uppercase; display: block; margin-bottom: var(--space-1);">
        APERÇU DE TEST
    </span>
    <h1 style="font-size: var(--font-2xl); font-weight: 600; color: var(--color-text-heading); font-family: var(--font-family-display); margin: 0 0 var(--space-2) 0; line-height: 1.2;">
        {{ $extraction['titre'] }}
    </h1>
    <div style="display: flex; align-items: center; gap: var(--space-2); color: #2e6027; font-size: var(--font-sm); font-weight: 500;">
        <x-icon name="check" size="16" />
        <span>Exécution terminée avec succès</span>
    </div>
</div>

{{-- Bandeau des paramètres utilisés --}}
<x-params-bandeau :items="$resultat['parametres_affiches'] ?? []" />

{{-- Avertissement si 0 ligne retournée (le test reste réussi) --}}
@if (($resultat['total_lignes'] ?? count($resultat['lignes'] ?? [])) === 0)
    <div style="margin-bottom: var(--space-4);" id="alerte-zero-ligne">
        <x-alert type="warning">
            Aucune ligne : vérifiez les valeurs saisies
        </x-alert>
    </div>
@endif

{{-- Carte des résultats --}}
<div class="card" style="padding: var(--space-6); background: var(--color-bg-card);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
        <div>
            <h2 style="font-size: var(--font-xl); font-weight: 600; color: var(--color-text-heading); margin: 0 0 var(--space-1) 0; font-family: var(--font-family-display);">
                Résultats
            </h2>
            <p style="font-size: var(--font-sm); color: var(--color-text-muted); margin: 0;">
                {{ number_format($resultat['total_lignes'] ?? count($resultat['lignes']), 0, ',', ' ') }} lignes retournées · aperçu limité à 100 lignes
            </p>
        </div>

        <div style="display: flex; align-items: center; gap: var(--space-3);">
            <x-badge-statut statut="test-reussi" id="badge-test-reussi" />

            @if ($peutPublier ?? false)
                <form method="POST" action="{{ route('admin.extractions.statut', $extraction['code']) }}" style="display: inline;" id="form-publier-extraction">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="action" value="publier">
                    <button type="submit" class="btn btn--primary" id="btn-publier-extraction" style="display: inline-flex; align-items: center; gap: var(--space-2);">
                        <x-icon name="check" size="16" />
                        <span>Publier cette extraction</span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Tableau de données paginé côté client (50 lignes/page, Alpine.js) --}}
    <x-results-table
        :colonnes="$resultat['colonnes'] ?? []"
        :lignes="$resultat['lignes'] ?? []"
        :page-size="50"
        empty-message="Aucun enregistrement ne correspond aux critères sélectionnés."
    />
</div>

@endsection
