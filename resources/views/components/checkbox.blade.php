{{--
    x-checkbox — Case à cocher
    Props :
      - name    : string
      - checked : bool
      - label   : texte
      - id      : string
--}}
@props([
    'name'    => '',
    'checked' => false,
    'label'   => '',
    'id'      => '',
])

<label class="checkbox">
    <input
        type="checkbox"
        name="{{ $name }}"
        id="{{ $id ?: $name }}"
        @checked($checked)
        {{ $attributes->except(['name', 'checked', 'label', 'id']) }}
    />
    <span class="checkbox__box" aria-hidden="true">
        <x-icon name="check" size="12" />
    </span>
    @if ($label)
        <span class="checkbox__label">{{ $label }}</span>
    @elseif ($slot->isNotEmpty())
        <span class="checkbox__label">{{ $slot }}</span>
    @endif
</label>
