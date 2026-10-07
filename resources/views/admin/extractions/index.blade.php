@extends('layouts.app')

@section('title', 'Extractions — Administration')

@section('content')

{{-- En-tête --}}
<x-page-header
    overtitle="ADMINISTRATION"
    title="Toutes les extractions"
    subtitle="Gérez les requêtes disponibles pour les équipes métier."
>
    <x-slot name="action">
        <a href="{{ route('admin.extractions.create') }}"
           class="btn btn--primary"
           id="btn-nouvelle-extraction">
            <x-icon name="plus" size="16" />
            Nouvelle extraction
        </a>
    </x-slot>
</x-page-header>

{{-- ── Barre de recherche et filtre ─────────────────────────────── --}}
<form method="GET" action="{{ route('admin.extractions.index') }}"
      class="search-filter-bar"
      id="form-recherche-extractions">

    <div class="search-input-wrapper">
        <x-icon name="search" size="16" class="search-icon" />
        <input
            type="text"
            name="q"
            id="input-recherche"
            class="input search-input"
            placeholder="Rechercher par code ou intitulé"
            value="{{ $q }}"
            autocomplete="off"
        />
    </div>

    <select name="statut" id="select-statut" class="input select-statut" onchange="this.form.submit()">
        <option value="tous" @selected($statut === '' || $statut === 'tous')>Tous les statuts</option>
        <option value="active"    @selected($statut === 'active')>Active</option>
        <option value="brouillon" @selected($statut === 'brouillon')>Brouillon</option>
        <option value="inactive"  @selected($statut === 'inactive')>Inactive</option>
    </select>

    <button type="submit" class="btn btn--outline btn--sm" id="btn-appliquer-filtres">
        Rechercher
    </button>
    @if ($q !== '' || ($statut !== '' && $statut !== 'tous'))
        <a href="{{ route('admin.extractions.index') }}"
           class="btn btn--ghost btn--sm"
           id="btn-effacer-filtres">
            <x-icon name="x" size="14" />
            Effacer
        </a>
    @endif
</form>

{{-- ── Tableau des extractions ──────────────────────────────────── --}}
<div class="card" id="card-liste-extractions">

    @php $nbFiltres = count($extractions); @endphp

    <div class="liste-compteur" id="compteur-extractions">
        @if ($q !== '' || ($statut !== '' && $statut !== 'tous'))
            {{ $nbFiltres }} {{ $nbFiltres > 1 ? 'extractions trouvées' : 'extraction trouvée' }}
            sur {{ $total }}
        @else
            {{ $total }} {{ $total > 1 ? 'extractions' : 'extraction' }}
        @endif
    </div>

    @if ($nbFiltres === 0)

        @if ($total === 0)
            {{-- ── État vide absolu (catalogue vide) ── --}}
            <div class="empty-state" id="empty-catalogue">
                <x-icon name="inbox" size="40" style="color:var(--color-text-muted)" />
                <p class="empty-state__title">Aucune extraction dans le catalogue</p>
                <p class="empty-state__subtitle">Créez votre première extraction pour commencer.</p>
                <a href="{{ route('admin.extractions.create') }}" class="btn btn--primary" id="btn-creer-premiere">
                    <x-icon name="plus" size="14" />
                    Créer la première extraction
                </a>
            </div>
        @else
            {{-- ── Aucun résultat de recherche ── --}}
            <div class="empty-state" id="empty-recherche">
                <x-icon name="search" size="40" style="color:var(--color-text-muted)" />
                <p class="empty-state__title">Aucun résultat</p>
                <p class="empty-state__subtitle">
                    Aucune extraction ne correspond à votre recherche.
                    <a href="{{ route('admin.extractions.index') }}" class="link" id="link-reinitialiser">Réinitialiser</a>
                </p>
            </div>
        @endif

    @else

        <table class="data-table" id="table-extractions">
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
                @foreach ($extractions as $ext)
                    <tr id="row-{{ strtolower($ext['code']) }}">
                        <td><x-chip-code :code="$ext['code']" /></td>
                        <td class="fw-medium">{{ $ext['titre'] }}</td>
                        <td class="text-muted">{{ $ext['description'] ?? '' }}</td>
                        <td>
                            <x-badge-statut :statut="$ext['statut']" />
                        </td>
                        <td class="text-muted">
                            @if (!empty($ext['modifie_le']))
                                {{ \Carbon\Carbon::parse($ext['modifie_le'])->locale('fr')->isoFormat('D MMM YYYY') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('admin.extractions.edit', $ext['code']) }}"
                                   class="link-modifier"
                                   id="link-modifier-{{ strtolower($ext['code']) }}">
                                    <x-icon name="edit" size="14" />
                                    Modifier
                                </a>

                                {{-- D22 : action rapide désactiver / réactiver --}}
                                @if (in_array($ext['statut'], ['active', 'inactive']))
                                    <form method="POST"
                                          action="{{ route('admin.extractions.toggle-statut', $ext['code']) }}"
                                          class="form-toggle-statut"
                                          id="form-toggle-{{ strtolower($ext['code']) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="btn btn--ghost btn--sm btn--danger-on-active"
                                                id="btn-toggle-{{ strtolower($ext['code']) }}"
                                                onclick="return confirm('{{ $ext['statut'] === 'active' ? 'Désactiver' : 'Réactiver' }} l\'extraction {{ $ext['code'] }} ?')">
                                            <x-icon name="{{ $ext['statut'] === 'active' ? 'eye-off' : 'eye' }}" size="14" />
                                            {{ $ext['statut'] === 'active' ? 'Désactiver' : 'Réactiver' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

    @endif
</div>

@endsection
