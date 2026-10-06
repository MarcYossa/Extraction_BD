{{--
    x-button — Bouton
    Props :
      - variant : 'primary' | 'secondary' | 'link' (défaut: 'primary')
      - size    : 'sm' | 'md' | 'lg' (défaut: 'md')
      - icon    : nom d'icône optionnel (affiché à gauche)
      - iconRight : nom d'icône optionnel (affiché à droite)
      - type    : 'button' | 'submit' | 'reset' (défaut: 'button')
      - disabled: bool
--}}
@props([
    'variant'   => 'primary',
    'size'      => 'md',
    'icon'      => null,
    'iconRight' => null,
    'type'      => 'button',
    'disabled'  => false,
    'tag'       => 'button',
])

@php
    $classes = 'btn btn--' . $variant;
    if ($size === 'sm') $classes .= ' btn--sm';
    if ($size === 'lg') $classes .= ' btn--lg';
@endphp

@if ($tag === 'a')
    <a {{ $attributes->class([$classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" size="16" />
        @endif
        {{ $slot }}
        @if ($iconRight)
            <x-icon :name="$iconRight" size="16" />
        @endif
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $disabled ? 'disabled' : '' }}
        {{ $attributes->class([$classes]) }}
    >
        @if ($icon)
            <x-icon :name="$icon" size="16" />
        @endif
        {{ $slot }}
        @if ($iconRight)
            <x-icon :name="$iconRight" size="16" />
        @endif
    </button>
@endif
