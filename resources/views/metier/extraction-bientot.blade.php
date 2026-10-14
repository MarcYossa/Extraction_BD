@extends('layouts.app')

@section('title', ($extraction['titre'] ?? 'Extraction') . ' — Espace Métier')

@section('content')

<x-page-header
    overtitle="ESPACE MÉTIER"
    :title="$extraction['titre'] ?? ''"
    backHref="{{ route('catalogue') }}"
    backLabel="Retour au catalogue"
/>

<div class="card" style="padding: var(--space-10) var(--space-8); text-align: center; margin-top: var(--space-6);">
    <x-chip-code :code="$extraction['code'] ?? ''" style="margin-bottom: var(--space-4);" />
    <h2 style="font-size: var(--text-xl); font-weight: var(--font-weight-semibold); color: var(--color-text-main); margin: var(--space-4) 0 var(--space-3);">
        Bientôt disponible
    </h2>
    <p style="font-size: var(--text-base); color: var(--color-text-secondary); max-width: 420px; margin: 0 auto var(--space-6);">
        L'interface d'exécution de cette extraction sera disponible prochainement (lot 8).
    </p>
    <a href="{{ route('catalogue') }}" class="btn btn--secondary" id="btn-retour-catalogue">
        <x-icon name="chevron-left" size="16" />
        Retour au catalogue
    </a>
</div>

@endsection
