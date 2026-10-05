{{--
    x-badge-statut — Pastille de statut d'extraction
    Props :
      - statut : 'active' | 'brouillon' | 'inactive' | 'test-reussi'
--}}
@props(['statut' => 'brouillon'])

@php
    $labels = [
        'active'       => 'Active',
        'brouillon'    => 'Brouillon',
        'inactive'     => 'Inactive',
        'test-reussi'  => 'Test réussi',
    ];
    $label = $labels[$statut] ?? ucfirst($statut);
@endphp

<span {{ $attributes->class(['badge-statut', "badge-statut--{$statut}"]) }}>
    {{ $label }}
</span>
