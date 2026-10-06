{{--
    x-modal — Modale pilotée par Alpine.js
    Usage :
      <x-modal id="confirm-test">
        <x-slot name="icon">...</x-slot>
        <x-slot name="title">Tester cette extraction ?</x-slot>
        <x-slot name="description">Les paramètres...</x-slot>
        <x-slot name="actions">
          <x-button variant="secondary" @click="$dispatch('close-modal', 'confirm-test')">Annuler</x-button>
          <x-button variant="primary">Lancer le test</x-button>
        </x-slot>
      </x-modal>

    Pour ouvrir : $dispatch('open-modal', 'confirm-test')
    Pour fermer : $dispatch('close-modal', 'confirm-test')
--}}
@props(['id' => ''])

<div
    x-data="{ open: false }"
    @if($id)
    x-on:open-modal.window="$event.detail === '{{ $id }}' ? open = true : null"
    x-on:close-modal.window="$event.detail === '{{ $id }}' ? open = false : null"
    @endif
    x-show="open"
    x-cloak
    class="modal-backdrop"
    @keydown.escape.window="open = false"
    @click.self="open = false"
    role="dialog"
    aria-modal="true"
    @if($id) aria-labelledby="modal-title-{{ $id }}" @endif
>
    <div
        class="modal"
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.stop
    >
        @if (isset($icon))
            <div class="modal__icon">
                {{ $icon }}
            </div>
        @endif

        <div class="modal__header">
            @if (isset($title))
                <h2 class="modal__title" @if($id) id="modal-title-{{ $id }}" @endif>
                    {{ $title }}
                </h2>
            @endif
            @if (isset($description))
                <p class="modal__desc">{{ $description }}</p>
            @endif
        </div>

        @if (isset($actions))
            <div class="modal__actions">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>
