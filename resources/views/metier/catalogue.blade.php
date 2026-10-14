@extends('layouts.app')

@section('title', 'Catalogue des extractions — Espace Métier')
@section('meta-description', 'Consultez et choisissez une extraction parmi les requêtes validées et publiées.')

@section('content')

{{-- ── En-tête de page ─────────────────────────────────────────────── --}}
<x-page-header
    overtitle="ESPACE MÉTIER"
    title="Catalogue des extractions"
/>

{{-- ── Corps du catalogue avec recherche Alpine.js ───────────────── --}}
<div
    x-data="catalogueSearch({{ json_encode($extractions) }})"
    id="catalogue-search-container"
>
    {{-- Titre + sous-titre --}}
    <h2 style="font-size: var(--text-2xl); font-weight: var(--font-weight-bold); color: var(--color-text-main); margin: 0 0 var(--space-2);">
        Choisir une extraction
    </h2>
    <p style="font-size: var(--text-base); color: var(--text-link); margin: 0 0 var(--space-6);">
        Les extractions ci-dessous ont été validées et publiées.
    </p>

    {{-- Barre de recherche --}}
    <div class="search-bar">
        <x-icon name="search" size="18" class="search-bar__icon" />
        <input
            type="search"
            class="search-bar__input"
            placeholder="Rechercher par code ou intitulé"
            x-model="query"
            @input="filter()"
            id="catalogue-recherche"
            aria-label="Rechercher une extraction par code ou intitulé"
            autocomplete="off"
        />
    </div>

    {{-- Grille ou état vide --}}
    <template x-if="resultats.length > 0">
        <div class="extraction-grid" id="catalogue-grille">
            <template x-for="ext in resultats" :key="ext.code">
                <a
                    :href="'/extractions/' + ext.code"
                    class="extraction-card"
                    :id="'card-' + ext.code.toLowerCase().replace(/_/g, '-')"
                    :aria-label="ext.titre"
                >
                    <div class="extraction-card__header">
                        <span class="chip-code" x-text="ext.code"></span>
                        <span class="extraction-card__arrow" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </span>
                    </div>
                    <div class="extraction-card__title" x-text="ext.titre"></div>
                    <div class="extraction-card__desc" x-text="ext.description"></div>
                    <div class="extraction-card__footer">
                        <span class="btn btn--secondary btn--sm extraction-card__btn">
                            Utiliser l'extraction
                        </span>
                    </div>
                </a>
            </template>
        </div>
    </template>

    {{-- Résultat de recherche vide --}}
    <template x-if="resultats.length === 0 && query.length > 0">
        <div class="empty-state" id="catalogue-recherche-vide">
            <x-icon name="search" size="48" class="empty-state__icon" />
            <div class="empty-state__title">Aucun résultat</div>
            <p class="empty-state__desc">
                Aucune extraction ne correspond à « <span x-text="query"></span> ».
                Essayez un autre terme de recherche.
            </p>
        </div>
    </template>

    {{-- Catalogue vide (aucune extraction publiée) --}}
    <template x-if="resultats.length === 0 && query.length === 0">
        <div class="empty-state" id="catalogue-etat-vide">
            <x-icon name="list" size="48" class="empty-state__icon" />
            <div class="empty-state__title">Aucune extraction disponible</div>
            <p class="empty-state__desc">
                Aucune extraction n'a encore été publiée. Revenez prochainement.
            </p>
        </div>
    </template>

</div>

@endsection

@push('scripts')
<script>
function catalogueSearch(extractions) {
    return {
        all: extractions,
        resultats: extractions,
        query: '',

        filter() {
            const q = this.normaliser(this.query);
            if (q === '') {
                this.resultats = this.all;
                return;
            }
            this.resultats = this.all.filter(ext => {
                return this.normaliser(ext.code        ?? '').includes(q)
                    || this.normaliser(ext.titre       ?? '').includes(q)
                    || this.normaliser(ext.description ?? '').includes(q);
            });
        },

        /**
         * Normalise une chaîne : minuscules + suppression des diacritiques.
         * Permet une recherche insensible à la casse et aux accents.
         */
        normaliser(str) {
            return str
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        },
    };
}
</script>
@endpush
