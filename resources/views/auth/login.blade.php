@extends('layouts.guest')

@section('title', 'Connexion')

@section('content')
<div class="login-box" style="display:flex; flex-direction:column; gap:var(--space-6);">

    {{-- En-tête de la carte de connexion --}}
    <div>
        <span style="font-size:var(--font-xs); font-weight:var(--weight-semibold); color:var(--color-primary); text-transform:uppercase; letter-spacing:0.05em;">
            ACCÈS SÉCURISÉ
        </span>
        <h2 style="font-size:var(--font-2xl); font-weight:var(--weight-semibold); color:var(--color-text-main); margin-top:var(--space-1);">
            Connexion
        </h2>
        <p style="font-size:var(--font-sm); color:var(--color-text-muted); margin-top:var(--space-1);">
            Saisissez vos identifiants applicatifs pour accéder aux extractions.
        </p>
    </div>

    {{-- Alertes de statut ou d'erreur --}}
    @if (session('status'))
        <x-alert type="info" title="Information">
            {{ session('status') }}
        </x-alert>
    @endif

    @if (session('error'))
        <x-alert type="warning" title="Session expirée">
            {{ session('error') }}
        </x-alert>
    @endif

    @if ($errors->any())
        <x-alert type="error" title="Erreur d'authentification">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </x-alert>
    @endif

    {{-- Formulaire de connexion --}}
    <form method="POST" action="{{ route('login') }}" style="display:flex; flex-direction:column; gap:var(--space-4);" novalidate>
        @csrf

        {{-- Identifiant --}}
        <x-form-field label="Identifiant" for="identifiant" required :error="$errors->first('identifiant')">
            <x-input
                id="identifiant"
                name="identifiant"
                type="text"
                :value="old('identifiant')"
                placeholder="prenom.nom"
                required
                autofocus
                :error="$errors->has('identifiant')"
            />
        </x-form-field>

        {{-- Mot de passe avec toggle Alpine.js --}}
        <div x-data="{ showPassword: false }">
            <x-form-field label="Mot de passe" for="password" required :error="$errors->first('password')">
                <div style="position:relative; display:flex; align-items:center;">
                    <input
                        :type="showPassword ? 'text' : 'password'"
                        id="password"
                        name="password"
                        class="input {{ $errors->has('password') ? 'input--error' : '' }}"
                        placeholder="••••••••"
                        required
                        style="padding-right: 45px; width: 100%;"
                    />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        style="position:absolute; right:12px; background:none; border:none; cursor:pointer; color:var(--color-text-muted); display:flex; align-items:center;"
                        :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                    >
                        <template x-if="!showPassword">
                            <x-icon name="eye" size="18" />
                        </template>
                        <template x-if="showPassword">
                            <x-icon name="x" size="18" />
                        </template>
                    </button>
                </div>
            </x-form-field>
        </div>

        {{-- Case à cocher "Se souvenir de moi" --}}
        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:var(--space-1);">
            <x-checkbox name="remember" id="remember" label="Se souvenir de moi" />
        </div>

        {{-- Bouton de soumission --}}
        <div style="margin-top:var(--space-2);">
            <x-button variant="primary" type="submit" style="width:100%; justify-content:center;">
                Se connecter
            </x-button>
        </div>
    </form>

</div>
@endsection
