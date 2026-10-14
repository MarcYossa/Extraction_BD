{{--
    x-params-bandeau — Bandeau récapitulatif des paramètres utilisés pour l'exécution (S07)
    Props :
      - items : array associatif [ 'Libellé' => 'Valeur affichée', ... ]
--}}
@props([
    'items' => [],
])

@if (! empty($items))
    <div class="params-bandeau">
        <span class="params-bandeau__label">PARAMÈTRES UTILISÉS</span>
        <div class="params-bandeau__items">
            @foreach ($items as $libelle => $valeur)
                <div class="params-bandeau__item">
                    <span class="params-bandeau__name">{{ $libelle }} :</span>
                    <span class="params-bandeau__value">{{ $valeur }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif
