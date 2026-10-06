{{--
    x-card — Carte
    Props :
      - variant : '' | 'hero' | 'extraction'
--}}
@props(['variant' => ''])

@php
    $cls = 'card';
    if ($variant) $cls .= " card--{$variant}";
@endphp

<div {{ $attributes->class([$cls]) }}>
    {{ $slot }}
</div>
