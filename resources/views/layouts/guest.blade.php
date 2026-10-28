<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'Connexion') — Extraction BD</title>
    <meta name="description" content="Connexion à la plateforme Extraction BD — BCPME." />
    @include('partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

{{-- Gabarit connexion : deux colonnes (sombre à gauche, formulaire à droite) --}}
<div class="guest-shell">

    {{-- Colonne gauche sombre --}}
    <div class="guest-panel-left">
        {{-- Logo --}}
        <div class="guest-panel-left__logo">
            <img src="{{ asset('BC-PME.jpg') }}" alt="Logo BCPME" class="guest-panel-left__logo-img"/>
            <span class="guest-panel-left__logo-name">Extraction BD</span>
        </div>

        {{-- Contenu principal --}}
        <div class="guest-panel-left__content">
            <span class="guest-panel-left__label">BCPME · Système interne</span>
            <h1 class="guest-panel-left__title">
                Les données utiles,<br>au bon moment.
            </h1>
            <p class="guest-panel-left__desc">
                Exécutez des extractions métier publiées, de manière sécurisée et sans requête SQL.
            </p>
        </div>

        {{-- Pied --}}
        <p class="guest-panel-left__footer">
            © {{ date('Y') }} BCPME — Usage interne uniquement
        </p>
    </div>

    {{-- Colonne droite formulaire --}}
    <div class="guest-panel-right">
        <div class="guest-form-wrapper">
            {{ $slot ?? '' }}
            @yield('content')
        </div>
    </div>

</div>

</body>
</html>
