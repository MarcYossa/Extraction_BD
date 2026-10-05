{{--
    x-input — Champ de saisie texte
    Props :
      - id      : string (requis pour l'accessibilité)
      - name    : string
      - type    : 'text' | 'password' | 'email' | 'number' | 'date' (défaut: 'text')
      - value   : valeur initiale
      - placeholder : texte d'aide
      - error   : bool (affiche la bordure rouge)
      - icon    : nom d'icône à afficher à gauche (ex: 'search')
--}}
@props([
    'type'        => 'text',
    'error'       => false,
    'icon'        => null,
    'value'       => '',
    'placeholder' => '',
])

@if ($icon)
    <div class="input-with-icon">
        <span class="input-with-icon__icon">
            <x-icon :name="$icon" size="16" />
        </span>
        <input
            type="{{ $type }}"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            {{ $attributes->class(['input', 'input--error' => $error]) }}
        />
    </div>
@else
    <input
        type="{{ $type }}"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->class(['input', 'input--error' => $error]) }}
    />
@endif
