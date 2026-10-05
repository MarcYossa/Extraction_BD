{{--
    x-chip-code — Badge code extraction (ex: CLI_001)
    Props :
      - code : string (code de l'extraction)
--}}
@props(['code' => ''])

<span {{ $attributes->class(['chip-code']) }}>
    {{ $code ?: $slot }}
</span>
