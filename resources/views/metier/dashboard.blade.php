@extends('layouts.app')

@section('title', 'Accueil — Espace Métier')
@section('meta-description', 'Trouvez et exécutez les extractions de données disponibles.')

@section('content')
@php
    $user = Auth::user();
    $prenom = $user ? $user->prenom : 'Utilisateur';
@endphp

{{-- ── En-tête de page ─────────────────────────────────────────────── --}}
<x-page-header
    overtitle="ESPACE MÉTIER"
    title="Bonjour, {{ $prenom }}"
/>

{{-- ── Bannière héro ───────────────────────────────────────────────── --}}
<div class="hero-banner" style="margin-bottom: var(--space-8);">
    <span class="hero-banner__overtitle">Données Métiers</span>
    <h2 class="hero-banner__title">Trouvez l'extraction dont vous avez besoin.</h2>
    <p class="hero-banner__subtitle">
        Sélectionnez une extraction publiée puis renseignez les paramètres demandés.
    </p>
    <div>
        <a href="{{ route('catalogue') }}" class="btn btn--primary" id="btn-parcourir-catalogue">
            Parcourir le catalogue
            <x-icon name="chevron-right" size="16" />
        </a>
    </div>
</div>

{{-- ── Section extractions récentes ──────────────────────────────── --}}
<div class="section-header">
    <div class="section-header__left">
        <span class="section-header__overtitle">Catalogue</span>
        <h2 class="section-header__title">Extractions disponibles</h2>
    </div>
    <a href="{{ route('catalogue') }}" class="section-header__link" id="lien-voir-catalogue">
        Voir le catalogue
        <x-icon name="chevron-right" size="14" />
    </a>
</div>

@if (count($recentes) === 0)
    {{-- État vide --}}
    <div class="empty-state" id="accueil-etat-vide">
        <x-icon name="list" size="48" class="empty-state__icon" />
        <div class="empty-state__title">Aucune extraction disponible</div>
        <p class="empty-state__desc">
            Aucune extraction n'a encore été publiée. Revenez prochainement.
        </p>
    </div>
@else
    <div class="extraction-grid" id="accueil-grille-extractions">
        @foreach ($recentes as $ext)
            <x-extraction-card :extraction="$ext" />
        @endforeach
    </div>
@endif

@endsection
