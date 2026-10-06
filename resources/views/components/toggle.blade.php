{{--
    x-toggle — Interrupteur Oui/Non
    Props :
      - name    : string (name de l'input hidden)
      - checked : bool
      - label   : texte affiché à droite
      - id      : string (pour l'accessibilité)
--}}
@props([
    'name'    => '',
    'checked' => false,
    'label'   => '',
    'id'      => '',
])

<label class="toggle" @if($id) for="{{ $id }}" @endif>
    <input
        type="checkbox"
        name="{{ $name }}"
        id="{{ $id ?: $name }}"
        @checked($checked)
        {{ $attributes->except(['name', 'checked', 'label', 'id']) }}
    />
    <span class="toggle__track"></span>
    @if ($label)
        <span class="toggle__label">{{ $label }}</span>
    @elseif ($slot->isNotEmpty())
        <span class="toggle__label">{{ $slot }}</span>
    @endif
</label>
