{{--
    x-page-header — En-tête de page avec sur-titre, titre, sous-titre et action
    Props :
      - overtitle : sur-titre (ex: 'ADMINISTRATION')
      - title     : titre principal (ex: 'Toutes les extractions')
      - subtitle  : sous-titre (ex: 'Gérez les requêtes...')
      - backHref  : URL du lien retour (optionnel)
      - backLabel : texte du lien retour
    Slot :
      - action    : bouton/contenu à droite (slot nommé)
--}}
@props([
    'overtitle' => null,
    'title'     => '',
    'subtitle'  => null,
    'backHref'  => null,
    'backLabel' => 'Retour',
])

<div class="page-header">
    <div class="page-header__text">
        @if ($backHref)
            <a href="{{ $backHref }}" class="back-link">
                <x-icon name="chevron-left" size="16" />
                {{ $backLabel }}
            </a>
        @endif
        @if ($overtitle)
            <span class="page-header__overtitle">{{ $overtitle }}</span>
        @endif
        <h1 class="page-header__title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="page-header__subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if (isset($action))
        <div class="page-header__action">
            {{ $action }}
        </div>
    @endif
</div>
