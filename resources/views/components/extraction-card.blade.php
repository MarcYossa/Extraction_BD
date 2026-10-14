{{--
    x-extraction-card — Carte d'une extraction dans la grille métier
    Props :
      - extraction : array (données d'une extraction du catalogue)
      - class      : classes CSS supplémentaires (optionnel)

    La carte entière est un lien vers /extractions/{code}.
--}}
@props(['extraction' => []])

@php
    $code        = $extraction['code'] ?? '';
    $titre       = $extraction['titre'] ?? '';
    $description = $extraction['description'] ?? '';
    $url         = route('extractions.show', ['code' => $code]);
@endphp

<a href="{{ $url }}"
   class="extraction-card {{ $attributes->get('class', '') }}"
   id="card-{{ Str::slug($code) }}"
   aria-label="{{ $titre }}">

    {{-- En-tête : puce code + bouton flèche rond --}}
    <div class="extraction-card__header">
        <x-chip-code :code="$code" />
        <span class="extraction-card__arrow" aria-hidden="true">
            <x-icon name="chevron-right" size="16" />
        </span>
    </div>

    {{-- Titre --}}
    <div class="extraction-card__title">{{ $titre }}</div>

    {{-- Description tronquée à 2 lignes --}}
    <div class="extraction-card__desc">{{ $description }}</div>

    {{-- Bouton "Utiliser l'extraction" --}}
    <div class="extraction-card__footer">
        <span class="btn btn--secondary btn--sm extraction-card__btn">
            Utiliser l'extraction
        </span>
    </div>

</a>
