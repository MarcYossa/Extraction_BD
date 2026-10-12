@extends('layouts.app')

@section('title', 'Créer une extraction — Administration')

@section('content')

{{-- En-tête de page S04 en création --}}
<x-page-header
    overtitle="ADMINISTRATION"
    title="Créer une extraction"
    subtitle="Définissez les informations, la requête et les paramètres d'exécution."
    :backHref="route('admin.extractions.index')"
    backLabel="Retour aux extractions"
>
    <x-slot:statusBadge>
        <x-badge-statut statut="brouillon" />
    </x-slot:statusBadge>
</x-page-header>

{{-- Formulaire partagé S04 --}}
@include('admin.extractions._form', [
    'formAction' => route('admin.extractions.store'),
    'isEdit'     => false,
    'extraction' => $extraction,
])

@endsection
