{{-- x-textarea — Zone de texte multiligne --}}
@props(['error' => false, 'rows' => 4])

<textarea
    rows="{{ $rows }}"
    {{ $attributes->class(['textarea', 'input--error' => $error]) }}
>{{ $slot }}</textarea>
