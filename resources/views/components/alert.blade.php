{{--
    x-alert — Alerte
    Props :
      - type  : 'success' | 'error' | 'warning' | 'info'
      - title : texte du titre (optionnel)
--}}
@props([
    'type'  => 'info',
    'title' => null,
])

@php
    $icons = [
        'success' => 'check',
        'error'   => 'x',
        'warning' => 'alert-triangle',
        'info'    => 'alert-triangle',
    ];
    $icon = $icons[$type] ?? 'alert-triangle';
@endphp

<div {{ $attributes->class(['alert', "alert--{$type}"]) }} role="alert">
    <span class="alert__icon">
        <x-icon :name="$icon" size="20" />
    </span>
    <div class="alert__content">
        @if ($title)
            <span class="alert__title">{{ $title }}</span>
        @endif
        @if ($slot->isNotEmpty())
            <span>{{ $slot }}</span>
        @endif
    </div>
</div>
