{{-- x-select — Liste déroulante --}}
@props(['error' => false])

<select {{ $attributes->class(['select', 'input--error' => $error]) }}>
    {{ $slot }}
</select>
