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

{{--
    Gabarit principal : sidebar (315 px) + topbar + contenu.
    L'utilisateur factice est injecté pour ce lot ; au lot 2,
    il sera remplacé par Auth::user().
--}}
@php
    $fakeUser = $fakeUser ?? [
        'prenom'     => 'Amine',
        'nom'        => 'M.',
        'role'       => 'admin',         // 'admin' | 'user'
        'initiales'  => 'AM',
        'role_label' => 'Administrateur',
    ];
    $role = $fakeUser['role'] ?? 'user';
    $isAdmin = $role === 'admin';
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
                   id="nav-nouvelle-extraction">
                    <x-icon name="plus" size="18" />
                    Nouvelle extraction
                </a>

            @else
                {{-- Menu utilisateur métier --}}
                <span class="sidebar__section-label">Espace métier</span>

                <a href="{{ route('dashboard') }}"
                   class="sidebar__nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}"
                   id="nav-accueil-metier">
                    <x-icon name="grid" size="18" />
                    Accueil
                </a>

                <a href="{{ route('catalogue') }}"
                   class="sidebar__nav-item {{ request()->routeIs('catalogue*') ? 'is-active' : '' }}"
                   id="nav-catalogue">
                    <x-icon name="list" size="18" />
                    Catalogue
                </a>
            @endif

        </nav>

        <hr class="sidebar__divider" />

        {{-- Pied de sidebar --}}
        <div class="sidebar__footer">
            {{-- Paramètres : masqué (D16 — aucune maquette) --}}
            {{-- <a href="#" class="sidebar__nav-item" id="nav-parametres">
                <x-icon name="settings" size="18" />
                Paramètres
            </a> --}}

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar__nav-item" id="nav-deconnexion">
                    <x-icon name="logout" size="18" />
                    Déconnexion
                </button>
            </form>
        </div>

    </aside>

    {{-- ── Zone principale ─────────────────────────────────────────────── --}}
    <div class="main-wrapper">

        {{-- Topbar --}}
        <header class="topbar" role="banner">
            <div class="topbar__header">
                <span class="topbar__overtitle">
                    {{ $isAdmin ? 'Administration' : 'Espace métier' }}
                </span>
                <span class="topbar__title">@yield('page-title', '')</span>
            </div>

            {{-- Profil --}}
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
            >
                <div class="topbar__user-info">
                    <span class="topbar__user-name">
                        {{ $fakeUser['prenom'] }} {{ $fakeUser['nom'] }} — {{ $fakeUser['role_label'] }}
                    </span>
                    <span class="topbar__user-org">BCPME</span>
                </div>
                <x-avatar :initials="$fakeUser['initiales']" size="md" />
                <x-icon name="chevron-down" size="16" class="topbar__chevron" />
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
