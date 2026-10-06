{{--
    x-stat-bandeau — Bandeau « Paramètres utilisés »
    Affiche un bandeau récapitulatif des paramètres d'une exécution.
    Props :
      - params : array associatif ['Libellé' => 'Valeur', ...]
--}}
@props(['params' => []])

<div class="stat-bandeau" {{ $attributes }}>
    <span class="stat-bandeau__label">Paramètres utilisés</span>
    @foreach ($params as $label => $value)
        <span class="stat-bandeau__item">
            {{ $label }} : <strong>{{ $value }}</strong>
        </span>
    @endforeach
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @endif
</div>
