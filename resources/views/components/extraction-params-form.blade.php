{{--
    x-extraction-params-form — Formulaire généré dynamiquement à partir des paramètres d'une extraction
    Props :
      - parametres     : array des définitions de paramètres
      - optionsByParam : array associatif [ nom_param => [ ['valeur' => ..., 'libelle' => ...], ... ] ]
      - values         : array associatif des valeurs pré-remplies
--}}
@props([
    'parametres'     => [],
    'optionsByParam' => [],
    'values'         => [],
])

<div class="card" style="padding: var(--space-6); background: var(--color-bg-card);">
    <div style="display: flex; flex-direction: column; gap: var(--space-1); margin-bottom: var(--space-6);">
        <h2 style="font-size: var(--font-lg); font-weight: 600; color: var(--color-text-heading); margin: 0; font-family: var(--font-family-display);">
            Paramètres d'exécution
        </h2>
        @php
            $hasRequired = collect($parametres)->contains(fn($p) => ! empty($p['obligatoire']));
        @endphp
        @if ($hasRequired)
            <span style="font-size: var(--font-xs); color: var(--color-text-muted);">
                <span style="color: var(--color-error); font-weight: bold;">*</span> Champ obligatoire
            </span>
        @endif
    </div>

    <div style="display: flex; flex-direction: column; gap: var(--space-5);">
        @forelse ($parametres as $param)
            @php
                $nom = $param['nom'] ?? '';
                $libelle = $param['libelle'] ?? $nom;
                $type = $param['type'] ?? 'texte';
                $obligatoire = ! empty($param['obligatoire']);
                $defaut = $param['defaut'] ?? null;
                $currentVal = old($nom, $values[$nom] ?? $defaut);

                if ($type === 'date' && ($currentVal === 'aujourd_hui' || ($currentVal === null && $defaut === 'aujourd_hui'))) {
                    $currentVal = now()->format('Y-m-d');
                }

                $err = $errors->first($nom) ?: $errors->first("params.{$nom}");
            @endphp

            <div class="form-field">
                {{-- Champ Interrupteur (Booléen) --}}
                @if ($type === 'booleen')
                    @php
                        $isChecked = (bool) filter_var($currentVal, FILTER_VALIDATE_BOOLEAN);
                    @endphp
                    <label class="form-field__label" style="margin-bottom: var(--space-2);">
                        {{ $libelle }}
                        @if ($obligatoire)
                            <span class="form-field__required" aria-hidden="true">*</span>
                        @endif
                    </label>
                    <div
                        x-data="{ checked: {{ $isChecked ? 'true' : 'false' }} }"
                        class="toggle-switch-field"
                    >
                        <input type="hidden" name="{{ $nom }}" :value="checked ? '1' : '0'">
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="checked"
                            @click="checked = !checked"
                            class="toggle-switch-field__btn"
                            :class="{ 'is-active': checked }"
                        >
                            <span class="toggle-switch-field__thumb"></span>
                        </button>
                        <span class="toggle-switch-field__label" x-text="checked ? 'Oui' : 'Non'">
                            {{ $isChecked ? 'Oui' : 'Non' }}
                        </span>
                    </div>

                {{-- Champ Liste déroulante --}}
                @elseif ($type === 'liste')
                    <label class="form-field__label" for="param-{{ $nom }}">
                        {{ $libelle }}
                        @if ($obligatoire)
                            <span class="form-field__required" aria-hidden="true">*</span>
                        @endif
                    </label>
                    @php
                        $listOpts = $optionsByParam[$nom] ?? [];
                        $optionToutes = $param['option_toutes'] ?? null;
                    @endphp
                    <select
                        name="{{ $nom }}"
                        id="param-{{ $nom }}"
                        class="input input-select"
                        @if ($obligatoire && empty($optionToutes)) required @endif
                    >
                        @if ($optionToutes)
                            <option
                                value="{{ $optionToutes }}"
                                @selected((string) $currentVal === (string) $optionToutes || $currentVal === null || $currentVal === '')
                            >
                                {{ $optionToutes }}
                            </option>
                        @elseif (! $obligatoire)
                            <option value="">— Sélectionner une option —</option>
                        @endif

                        @foreach ($listOpts as $opt)
                            <option
                                value="{{ $opt['valeur'] }}"
                                @selected((string) $currentVal === (string) $opt['valeur'])
                            >
                                {{ $opt['libelle'] }}
                            </option>
                        @endforeach
                    </select>

                {{-- Champ Date --}}
                @elseif ($type === 'date')
                    <label class="form-field__label" for="param-{{ $nom }}">
                        {{ $libelle }}
                        @if ($obligatoire)
                            <span class="form-field__required" aria-hidden="true">*</span>
                        @endif
                    </label>
                    <input
                        type="date"
                        name="{{ $nom }}"
                        id="param-{{ $nom }}"
                        class="input"
                        value="{{ $currentVal }}"
                        @if ($obligatoire) required @endif
                    >

                {{-- Champ Nombre --}}
                @elseif ($type === 'nombre')
                    <label class="form-field__label" for="param-{{ $nom }}">
                        {{ $libelle }}
                        @if ($obligatoire)
                            <span class="form-field__required" aria-hidden="true">*</span>
                        @endif
                    </label>
                    <input
                        type="number"
                        name="{{ $nom }}"
                        id="param-{{ $nom }}"
                        class="input"
                        value="{{ $currentVal }}"
                        @if (isset($param['min'])) min="{{ $param['min'] }}" @endif
                        @if (isset($param['max'])) max="{{ $param['max'] }}" @endif
                        @if ($obligatoire) required @endif
                    >

                {{-- Champ Texte par défaut --}}
                @else
                    <label class="form-field__label" for="param-{{ $nom }}">
                        {{ $libelle }}
                        @if ($obligatoire)
                            <span class="form-field__required" aria-hidden="true">*</span>
                        @endif
                    </label>
                    <input
                        type="text"
                        name="{{ $nom }}"
                        id="param-{{ $nom }}"
                        class="input"
                        value="{{ $currentVal }}"
                        @if (isset($param['longueur_max'])) maxlength="{{ $param['longueur_max'] }}" @endif
                        @if ($obligatoire) required @endif
                    >
                @endif

                @if ($err)
                    <span class="form-field__error" role="alert" style="margin-top: var(--space-1); display: flex; align-items: center; gap: var(--space-1); color: var(--color-error); font-size: var(--font-xs);">
                        <x-icon name="alert-triangle" size="14" />
                        {{ $err }}
                    </span>
                @endif
            </div>
        @empty
            <p style="color: var(--color-text-muted); font-size: var(--font-sm); margin: 0;">
                Cette extraction ne comporte aucun paramètre d'exécution.
            </p>
        @endforelse
    </div>
</div>
