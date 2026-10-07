@extends('layouts.app')

@section('title', $titre . ' — Administration')

@section('content')

<x-page-header
    overtitle="ADMINISTRATION"
    :title="$titre"
    :backHref="route('admin.extractions.index')"
    backLabel="Retour à la liste"
/>

<div class="card" style="text-align:center; padding:var(--space-12) var(--space-6);">
    <x-icon name="clock" size="48" style="color:var(--color-text-muted); margin-bottom:var(--space-4);" />
    <h2 style="font-size:var(--font-xl); font-weight:600; margin-bottom:var(--space-2);">
        Bientôt disponible
    </h2>
    <p class="text-muted" style="max-width:420px; margin:0 auto var(--space-6);">
        Cette fonctionnalité sera disponible dans le lot suivant.
    </p>
    <a href="{{ route('admin.extractions.index') }}" class="btn btn--outline" id="btn-retour-bientot">
        <x-icon name="chevron-left" size="14" />
        Retour à la liste
    </a>
</div>

@endsection
