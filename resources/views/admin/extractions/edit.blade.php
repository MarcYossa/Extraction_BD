@extends('layouts.app')

@section('title', "Modifier l'extraction {$extraction['code']} — Administration")

@section('content')

{{-- En-tête de page S04 en édition --}}
<x-page-header
    overtitle="ADMINISTRATION"
    :title="'Modifier l\'extraction ' . $extraction['code']"
    subtitle="Définissez les informations, la requête et les paramètres d'exécution."
    :backHref="route('admin.extractions.index')"
    backLabel="Retour aux extractions"
>
    <x-slot:statusBadge>
        <x-badge-statut :statut="$extraction['statut'] ?? 'brouillon'" />
    </x-slot:statusBadge>
</x-page-header>

{{-- Formulaire partagé S04 --}}
@include('admin.extractions._form', [
    'formAction' => route('admin.extractions.update', ['code' => $extraction['code']]),
    'isEdit'     => true,
    'extraction' => $extraction,
    'isCurrentlyActive' => $isCurrentlyActive ?? false,
])

@endsection
