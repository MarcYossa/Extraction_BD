@extends('layouts.app')

@section('title', "Exécuter l'extraction {$extraction['code']} — Espace Métier")

@section('content')

{{-- Lien retour au catalogue --}}
<div style="margin-bottom: var(--space-4);">
    <a href="{{ route('catalogue') }}" class="back-link" id="link-retour-catalogue">
        ‹ Retour au catalogue
    </a>
</div>

{{-- En-tête M03 (03-executer-extraction.png) : 3 blocs (code espacé, titre, description) --}}
<div style="display: flex; align-items: flex-start; gap: var(--space-6); margin-bottom: var(--space-8); flex-wrap: wrap;">
    <div style="flex-shrink: 0; min-width: 140px;">
        <span class="badge badge--code" style="font-family: var(--font-family-mono); font-size: var(--font-sm); font-weight: 700; letter-spacing: 0.1em; color: #476a82; text-transform: uppercase;">
            {{ $extraction['code'] }}
        </span>
    </div>
    <div style="flex: 1; min-width: 250px;">
        <h1 style="font-size: var(--font-2xl); font-weight: 600; color: var(--color-text-heading); font-family: var(--font-family-display); margin: 0 0 var(--space-1) 0; line-height: 1.2;">
            {{ $extraction['titre'] }}
        </h1>
    </div>
    <div style="flex: 1.2; min-width: 280px;">
        <p style="font-size: var(--font-sm); color: var(--color-text-muted); margin: 0; line-height: 1.5;">
            {{ $extraction['description'] }}
        </p>
    </div>
</div>

{{-- Messages d'alerte / erreur d'exécution éventuels --}}
@if (session('error'))
    <div class="alert alert--error" style="margin-bottom: var(--space-6);" role="alert">
        {{ session('error') }}
    </div>
@endif

{{-- Formulaire d'exécution --}}
<form
    method="POST"
    action="{{ route('extractions.execute', $extraction['code']) }}"
    id="form-execution"
    x-data="{ submitting: false }"
    @submit="if (submitting) { $event.preventDefault(); return false; } submitting = true;"
>
    @csrf

    <div style="display: flex; gap: var(--space-8); align-items: flex-start; flex-wrap: wrap;">
        {{-- Colonne gauche : Paramètres d'exécution --}}
        <div style="flex: 1; min-width: 320px;">
            <x-extraction-params-form
                :parametres="$extraction['parametres'] ?? []"
                :options-by-param="$optionsByParam ?? []"
                :values="$values ?? []"
            />
        </div>

        {{-- Colonne droite : Encart Exécution sécurisée + Bouton Exécuter l'extraction --}}
        <div style="width: 300px; flex-shrink: 0; display: flex; flex-direction: column;">
            <div class="card-callout">
                <div class="card-callout__header">
                    <x-icon name="database" size="18" />
                    <span>Exécution sécurisée</span>
                </div>
                <div class="card-callout__desc">
                    Cette opération consulte les données sans les modifier.
                </div>
            </div>

            <button
                type="submit"
                class="btn btn--primary"
                style="width: 100%; justify-content: center; gap: var(--space-2); margin-top: var(--space-4); padding: var(--space-3);"
                id="btn-executer"
                :disabled="submitting"
            >
                <template x-if="!submitting">
                    <div style="display: inline-flex; align-items: center; gap: var(--space-2);">
                        <x-icon name="play" size="16" />
                        <span>Exécuter l'extraction</span>
                    </div>
                </template>
                <template x-if="submitting">
                    <div style="display: inline-flex; align-items: center; gap: var(--space-2);">
                        <span class="spinner" style="width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; display: inline-block; animation: spin 0.8s linear infinite;"></span>
                        <span>Exécution en cours…</span>
                    </div>
                </template>
            </button>
        </div>
    </div>
</form>

<style>
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

@endsection
