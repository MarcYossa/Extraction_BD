<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'Extraction BD') — BCPME</title>
    <meta name="description" content="@yield('meta-description', 'Plateforme interne d\'extractions de données BCPME.')" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

@php
    $authUser = Auth::user();
    $userDisplayName = $authUser ? $authUser->nom_affiche : ($fakeUser['prenom'] ?? 'Amine') . ' ' . ($fakeUser['nom'] ?? 'M.');
    $userRoleLabel   = $authUser ? $authUser->role_label : ($fakeUser['role_label'] ?? 'Administrateur');
    $userInitials    = $authUser ? $authUser->initiales : ($fakeUser['initiales'] ?? 'AM');
    $isAdmin         = $authUser ? $authUser->isAdmin() : (($fakeUser['role'] ?? 'admin') === 'admin');
@endphp

<div class="app-shell" x-data>

    {{-- ── Sidebar ──────────────────────────────────────────────────────── --}}
    <aside class="sidebar" aria-label="Navigation principale">

        {{-- Logo --}}
        <div class="sidebar__logo">
            <x-icon name="database" size="24" style="color:var(--color-accent)" />
            <div>
                <div class="sidebar__logo-name">Extraction BD</div>
                <div class="sidebar__logo-org">BCPME</div>
            </div>
        </div>

        {{-- Navigation selon le rôle --}}
        <nav class="sidebar__nav" aria-label="Menu principal">

            @if ($isAdmin)
                {{-- Menu administrateur technique --}}
                <span class="sidebar__section-label">Administration</span>

                <a href="{{ route('admin.dashboard') }}"
                   class="sidebar__nav-item {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}"
                   id="nav-accueil">
                    <x-icon name="grid" size="18" />
                    Accueil
                </a>

                <a href="{{ route('admin.extractions.index') }}"
                   class="sidebar__nav-item {{ request()->routeIs('admin.extractions.*') ? 'is-active' : '' }}"
                   id="nav-extractions">
                    <x-icon name="list" size="18" />
                    Extractions
                </a>

                <a href="{{ route('admin.extractions.create') }}"
                   class="sidebar__nav-item {{ request()->routeIs('admin.extractions.create') ? 'is-active' : '' }}"
                   id="nav-creer">
                    <x-icon name="plus" size="18" />
                    Nouvelle extraction
                </a>
            @else
                {{-- Menu utilisateur métier --}}
                <span class="sidebar__section-label">Espace Métier</span>

                <a href="{{ route('dashboard') }}"
                   class="sidebar__nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}"
                   id="nav-metier-accueil">
                    <x-icon name="grid" size="18" />
                    Accueil
                </a>

                <a href="{{ route('catalogue') }}"
                   class="sidebar__nav-item {{ request()->routeIs('catalogue') ? 'is-active' : '' }}"
                   id="nav-metier-catalogue">
                    <x-icon name="list" size="18" />
                    Catalogue d'extractions
                </a>
            @endif

        </nav>

        {{-- Bas de la sidebar --}}
        <div class="sidebar__footer">
            <div class="sidebar__version">Extraction BD v1.0</div>
            <div class="sidebar__env">Environnement de test</div>
        </div>

    </aside>

    {{-- ── Zone principale ────────────────────────────────────────────────── --}}
    <div class="main-wrapper">

        {{-- Barre du haut (Topbar) --}}
        <header class="topbar">
            {{-- Fil d'Ariane ou titre rapide --}}
            <div class="topbar__left">
                <span class="topbar__tag">
                    {{ $isAdmin ? 'Administration technique' : 'Espace Métier' }}
                </span>
            </div>

            {{-- Profil utilisateur avec menu déroulant et déconnexion --}}
            <div
                class="topbar__user"
                x-data="{ open: false }"
                @click="open = !open"
                @keydown.escape="open = false"
                @click.outside="open = false"
                role="button"
                tabindex="0"
                aria-haspopup="true"
                :aria-expanded="open"
                id="topbar-user-menu"
                style="position:relative; cursor:pointer;"
            >
                <div class="topbar__user-info">
                    <span class="topbar__user-name">
                        {{ $userDisplayName }} — {{ $userRoleLabel }}
                    </span>
                    <span class="topbar__user-org">BCPME</span>
                </div>
                <x-avatar :initials="$userInitials" size="md" />
                <x-icon name="chevron-down" size="16" class="topbar__chevron" />

                {{-- Menu déroulant Déconnexion --}}
                <div
                    x-show="open"
                    x-transition
                    style="position:absolute; top:calc(100% + 8px); right:0; background:var(--color-bg-card); border:1px solid var(--color-border-subtle); border-radius:var(--radius-md); box-shadow:var(--shadow-md); padding:var(--space-2); min-width:180px; z-index:50;"
                >
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            style="display:flex; align-items:center; gap:var(--space-2); width:100%; padding:var(--space-2) var(--space-3); border:none; background:none; color:var(--color-text-main); cursor:pointer; text-align:left; font-size:var(--font-sm); border-radius:var(--radius-sm);"
                        >
                            <x-icon name="logout" size="16" />
                            Déconnexion
                        </button>
                    </form>
                </div>
            </div>
        </header>

        {{-- Contenu de page --}}
        <main class="page-content" id="main-content">
            @if (session('success'))
                <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
            @endif
            @if (session('error'))
                <x-alert type="error" class="mb-4">{{ session('error') }}</x-alert>
            @endif

            {{ $slot ?? '' }}
            @yield('content')
        </main>

    </div>{{-- /.main-wrapper --}}

</div>{{-- /.app-shell --}}

{{-- Modales globales éventuelles --}}
{{ $modals ?? '' }}

</body>
</html>
