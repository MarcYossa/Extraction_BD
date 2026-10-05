{{--
    x-avatar — Avatar avec initiales
    Props :
      - initials : string (ex: 'AM' pour Amine M.)
      - size     : 'sm' | 'md' | 'lg' (défaut: 'md')
--}}
@props([
    'initials' => '?',
    'size'     => 'md',
])

@php
    $cls = 'avatar';
    if ($size === 'sm') $cls .= ' avatar--sm';
    if ($size === 'lg') $cls .= ' avatar--lg';
@endphp

<span {{ $attributes->class([$cls]) }} aria-hidden="true">
    {{ strtoupper(substr($initials, 0, 2)) }}
</span>
