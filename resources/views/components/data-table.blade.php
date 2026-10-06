{{--
    x-data-table — Tableau de données
    Props :
      - columns : array de ['key' => string, 'label' => string, 'class' => string optionnel]
      - rows    : collection/array de données (chaque ligne est un objet/array)
      - count   : int (nombre total, affiché au-dessus)
      - countLabel : string (ex: 'extraction', 'extractions')
      - emptyMessage : string
    Usage : ce composant affiche le squelette ; les cellules spéciales
    (chip-code, badge, actions) sont définies dans des scoped slots ou
    passées directement en HTML via le slot body.
    Pour une version simple, passer $slot pour le <tbody> entier.
--}}
@props([
    'columns'      => [],
    'count'        => null,
    'countLabel'   => 'résultat',
    'emptyMessage' => 'Aucun résultat.',
])

<div class="data-table-wrapper">
    @if ($count !== null)
        <p class="data-table-count">
            @if (is_numeric($count))
                {{ $count }} {{ Str::plural($countLabel, (int) $count) }}
            @else
                {{ $count }}
            @endif
        </p>
    @endif

    <table class="data-table" role="table">
        @if (count($columns) > 0)
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        <th scope="col" @class(['col-' . ($col['key'] ?? ''), $col['class'] ?? ''])>
                            {{ $col['label'] ?? '' }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @else
                <tr>
                    <td colspan="{{ max(1, count($columns)) }}" style="text-align:center;padding:var(--space-8) var(--space-4);">
                        <span style="color:var(--text-muted)">{{ $emptyMessage }}</span>
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
