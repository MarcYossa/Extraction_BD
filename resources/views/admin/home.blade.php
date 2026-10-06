@extends('layouts.app')

@section('title', 'Accueil — Administration')

@section('content')

{{-- En-tête de page --}}
<x-page-header
    overtitle="ADMINISTRATION"
    title="Bonjour, {{ explode(' ', auth()->user()->nom_affiche)[0] }}"
/>

{{-- ── Carte principale : Gestion des extractions ──────────────── --}}
<div class="card home-hero-card" style="margin-bottom: var(--space-6);">
    <span class="page-header__overtitle">GESTION DES EXTRACTIONS</span>
    <h2 class="home-hero__title">Pilotez les extractions publiées.</h2>
    <p class="home-hero__desc">Créez, testez et publiez les requêtes mises à disposition des utilisateurs métier.</p>
    <div class="home-hero__actions">
        <a href="{{ route('admin.extractions.create') }}"
           class="btn btn--primary"
           id="btn-nouvelle-extraction-home">
            <x-icon name="plus" size="16" />
            Nouvelle extraction
        </a>
        <a href="{{ route('admin.extractions.index') }}"
           class="btn btn--outline"
           id="btn-voir-extractions-home">
            Voir les extractions
        </a>
    </div>
</div>

{{-- ── Carte : Dernières modifications ─────────────────────────── --}}
<div class="card">
    <div class="card-section-header">
        <div>
            <span class="page-header__overtitle">À SUIVRE</span>
            <h2 class="card-section-title">Dernières modifications</h2>
        </div>
        <a href="{{ route('admin.extractions.index') }}" class="link-voir-toutes" id="link-voir-toutes">
            Voir toutes
            <x-icon name="chevron-right" size="14" />
        </a>
    </div>

    @if (count($dernieres) === 0)
        <div class="empty-state" id="empty-dernieres">
            <x-icon name="inbox" size="32" style="color:var(--color-text-muted)" />
            <p>Aucune extraction dans le catalogue.</p>
            <a href="{{ route('admin.extractions.create') }}" class="btn btn--primary btn--sm">
                <x-icon name="plus" size="14" />
                Créer la première extraction
            </a>
        </div>
    @else
        <table class="data-table" id="table-dernieres-modifications">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Titre</th>
                    <th>Description</th>
                    <th>Statut</th>
                    <th>Dernière modification</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($dernieres as $ext)
                    <tr id="row-home-{{ strtolower($ext['code']) }}">
                        <td><x-chip-code :code="$ext['code']" /></td>
                        <td class="fw-medium">{{ $ext['titre'] }}</td>
                        <td class="text-muted">{{ $ext['description'] ?? '' }}</td>
                        <td><x-badge-statut :statut="$ext['statut']" /></td>
                        <td class="text-muted">
                            @if (!empty($ext['modifie_le']))
                                {{ \Carbon\Carbon::parse($ext['modifie_le'])->locale('fr')->isoFormat('D MMM YYYY') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.extractions.edit', $ext['code']) }}"
                               class="link-modifier"
                               id="link-modifier-home-{{ strtolower($ext['code']) }}">
                                <x-icon name="edit" size="14" />
                                Modifier
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@endsection
