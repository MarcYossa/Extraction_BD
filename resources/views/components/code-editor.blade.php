{{--
    x-code-editor — Bloc d'affichage/édition de requête SQL
    Props :
      - mode    : 'display' | 'edit' (défaut: 'display')
      - name    : name de la textarea (mode edit)
      - id      : id de la textarea
      - value   : contenu SQL
--}}
@props([
    'mode'  => 'display',
    'name'  => 'sql',
    'id'    => 'sql',
    'value' => '',
])

<div class="code-editor" {{ $attributes }}>
    @if ($mode === 'edit')
        <textarea
            name="{{ $name }}"
            id="{{ $id }}"
            class="code-editor__content"
            spellcheck="false"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
        >{{ $value ?: $slot }}</textarea>
    @else
        <pre class="code-editor__content">{{ $value ?: $slot }}</pre>
    @endif
</div>
