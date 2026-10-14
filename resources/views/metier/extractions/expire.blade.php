@extends('layouts.app')

@section('title', "Résultat expiré — Espace Métier")

@section('content')

{{-- Lien retour aux paramètres --}}
<div style="margin-bottom: var(--space-4);">
    <a href="{{ route('extractions.show', $code) }}" class="back-link" id="link-retour-parametres">
        ‹ Retour aux paramètres
    </a>
</div>

{{-- Message résultat expiré --}}
<div class="card" style="padding: var(--space-8); text-align: center; max-width: 600px; margin: var(--space-8) auto; background: var(--color-bg-card);">
    <div style="margin-bottom: var(--space-4); color: var(--color-text-muted); display: flex; justify-content: center;">
        <x-icon name="clock" size="48" />
    </div>
    <h1 style="font-size: var(--font-xl); font-weight: 600; color: var(--color-text-heading); margin-bottom: var(--space-2); font-family: var(--font-family-display);">
        Ce résultat a expiré
    </h1>
    <p style="color: var(--color-text-muted); font-size: var(--font-sm); margin-bottom: var(--space-6); line-height: 1.5;">
        Les résultats d'extraction sont conservés pendant 15 minutes pour garantir la fraîcheur et la sécurité de vos données. Veuillez relancer l'extraction.
    </p>
    <div>
        <a href="{{ route('extractions.show', $code) }}" class="btn btn--primary" id="btn-relancer-extraction">
            Relancer l'extraction
        </a>
    </div>
</div>

@endsection
