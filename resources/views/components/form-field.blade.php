{{--
    x-form-field — Groupe label + champ + erreur
    Props :
      - label    : texte de l'étiquette
      - for      : id de l'input associé
      - required : bool (affiche l'astérisque rouge)
      - error    : message d'erreur (string, null si pas d'erreur)
      - hint     : texte d'aide sous le champ
--}}
@props([
    'label'    => '',
    'for'      => '',
    'required' => false,
    'error'    => null,
    'hint'     => null,
])

<div class="form-field">
    @if ($label)
        <label class="form-field__label" @if($for) for="{{ $for }}" @endif>
            {{ $label }}
            @if ($required)
                <span class="form-field__required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint && !$error)
        <span class="form-field__hint">{{ $hint }}</span>
    @endif

    @if ($error)
        <span class="form-field__error" role="alert">
            <x-icon name="alert-triangle" size="14" />
            {{ $error }}
        </span>
    @endif
</div>
