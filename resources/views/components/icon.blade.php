{{--
    x-icon — Composant d'icône SVG
    Usage : <x-icon name="grid" class="w-5 h-5" />
    Les fichiers SVG sont dans resources/svg/
    Props :
      - name (string, requis) : nom du fichier SVG sans extension
      - class (string, optionnel) : classes CSS supplémentaires
      - size (string, optionnel) : '16' | '18' | '20' | '24' — taille en px (défaut 20)
      - aria-hidden (bool, optionnel, défaut true)
--}}
@props([
    'name',
    'size' => '20',
    'ariaHidden' => 'true',
])

@php
    $svgPath = resource_path("svg/{$name}.svg");
    $svgContent = file_exists($svgPath) ? file_get_contents($svgPath) : null;
@endphp

@if ($svgContent)
    <span
        {{ $attributes->class(['icon'])->merge(['style' => "width:{$size}px;height:{$size}px;display:inline-flex;align-items:center;flex-shrink:0"]) }}
        aria-hidden="{{ $ariaHidden }}"
        role="img"
    >
        {!! $svgContent !!}
    </span>
@endif
