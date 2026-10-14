@extends('layouts.app')

@section('title', "Test de l'extraction {$extraction['code']} — Administration")

@section('content')

{{-- Lien retour à l'édition --}}
<div style="margin-bottom: var(--space-4);">
    <a href="{{ route('admin.extractions.edit', $extraction['code']) }}" class="back-link" id="link-retour-edition">
        ‹ Retour à l'édition
    </a>
</div>

{{-- En-tête S06 (07-test-parametres.png) --}}
<div style="display: flex; align-items: flex-start; gap: var(--space-6); margin-bottom: var(--space-8); flex-wrap: wrap;">
    <div style="flex-shrink: 0; min-width: 180px;">
        <span style="font-size: var(--font-xs); font-weight: 700; letter-spacing: 0.1em; color: #476a82; text-transform: uppercase; display: block; margin-bottom: var(--space-1);">
            APERÇU AVANT PUBLICATION
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

{{-- Formulaire d'exécution du test --}}
<form method="POST" action="{{ route('admin.extractions.test.execute', $extraction['code']) }}" id="form-test-execution">
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

        {{-- Colonne droite : Encart Exécution sécurisée + Bouton Exécuter le test --}}
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
                id="btn-executer-test"
            >
                <x-icon name="play" size="16" />
                <span>Exécuter le test</span>
            </button>

            <p style="text-align: center; font-size: var(--font-xs); color: var(--color-text-muted); margin-top: var(--space-2); margin-bottom: 0;">
                L'aperçu est limité aux 100 premières lignes.
            </p>
        </div>
    </div>
</form>

@endsection
