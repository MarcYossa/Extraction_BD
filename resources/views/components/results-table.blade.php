{{--
    x-results-table — Tableau de résultats avec pagination côté client Alpine.js (50 lignes/page, D8)
    Props :
      - colonnes     : array des définitions de colonnes (cle, libelle, format)
      - lignes       : array des lignes de données (brutes ou pré-formatées)
      - pageSize     : int (50 par défaut)
      - emptyMessage : string en cas d'absence de résultat
--}}
@props([
    'colonnes'     => [],
    'lignes'       => [],
    'pageSize'     => 50,
    'emptyMessage' => 'Aucun résultat.',
])

@php
    $totalLignes = count($lignes);

    // Normaliser les colonnes (clés en minuscules)
    $colsFormatted = [];
    foreach ($colonnes as $col) {
        $colsFormatted[] = [
            'cle'     => strtolower($col['cle'] ?? ''),
            'libelle' => $col['libelle'] ?? $col['cle'] ?? '',
            'format'  => $col['format'] ?? 'texte',
        ];
    }

    // Si aucune colonne n'est fournie mais qu'il y a des lignes
    if (empty($colsFormatted) && ! empty($lignes)) {
        $firstRow = (array) $lignes[0];
        foreach (array_keys($firstRow) as $k) {
            $colsFormatted[] = [
                'cle'     => strtolower($k),
                'libelle' => strtoupper($k),
                'format'  => 'texte',
            ];
        }
    }

    // Normaliser les lignes : conversion en tableau avec clés minuscules
    $normalizedRows = [];
    foreach ($lignes as $row) {
        $normalizedRows[] = array_change_key_case((array) $row, CASE_LOWER);
    }
@endphp

<div
    class="results-table-container"
    x-data="{
        currentPage: 1,
        pageSize: {{ (int) $pageSize }},
        totalRows: {{ $totalLignes }},
        rows: {{ json_encode($normalizedRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }},
        cols: {{ json_encode($colsFormatted, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }},
        get totalPages() {
            return Math.ceil(this.totalRows / this.pageSize) || 1;
        },
        get pagedRows() {
            let start = (this.currentPage - 1) * this.pageSize;
            return this.rows.slice(start, start + this.pageSize);
        },
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },
        isStatutActif(val) {
            if (!val) return false;
            let s = String(val).toLowerCase();
            return s === 'actif' || s === 'active' || s === '1' || s === 'ouvert';
        }
    }"
>
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <template x-for="col in cols" :key="col.cle">
                        <th
                            scope="col"
                            :style="(col.format === 'montant' || col.format === 'nombre') ? 'text-align: right;' : ''"
                            x-text="col.libelle"
                        ></th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <template x-if="totalRows === 0">
                    <tr>
                        <td :colspan="Math.max(1, cols.length)" style="text-align: center; padding: var(--space-8); color: var(--color-text-muted);">
                            {{ $emptyMessage }}
                        </td>
                    </tr>
                </template>

                <template x-for="(row, idx) in pagedRows" :key="idx">
                    <tr>
                        <template x-for="col in cols" :key="col.cle">
                            <td :style="(col.format === 'montant' || col.format === 'nombre') ? 'text-align: right;' : ''">
                                <template x-if="col.format === 'code'">
                                    <span class="chip-code" x-text="row[col.cle] ?? '—'"></span>
                                </template>
                                <template x-if="col.format === 'statut'">
                                    <span class="statut-badge-cell">
                                        <span
                                            class="statut-badge-cell__dot"
                                            :class="isStatutActif(row[col.cle]) ? 'statut-badge-cell__dot--actif' : 'statut-badge-cell__dot--inactif'"
                                        ></span>
                                        <span x-text="row[col.cle] ?? '—'"></span>
                                    </span>
                                </template>
                                <template x-if="col.format !== 'code' && col.format !== 'statut'">
                                    <span x-text="row[col.cle] ?? '—'"></span>
                                </template>
                            </td>
                        </template>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- Pagination côté client Alpine --}}
    <div
        class="table-pagination"
        x-show="totalPages > 1"
        style="display: flex; align-items: center; justify-content: space-between; padding: var(--space-4) 0; border-top: 1px solid var(--color-border-subtle); margin-top: var(--space-4);"
    >
        <div style="font-size: var(--font-sm); color: var(--color-text-muted);">
            Affichage de <span x-text="((currentPage - 1) * pageSize) + 1"></span> à <span x-text="Math.min(currentPage * pageSize, totalRows)"></span> sur <span x-text="totalRows"></span> lignes
        </div>
        <div style="display: flex; align-items: center; gap: var(--space-2);">
            <button
                type="button"
                class="btn btn--secondary btn--sm"
                @click="prevPage"
                :disabled="currentPage === 1"
                style="padding: 4px 10px; font-size: var(--font-sm);"
            >
                ‹ Précédent
            </button>
            <span style="font-size: var(--font-sm); color: var(--color-text-body); padding: 0 var(--space-2);">
                Page <strong x-text="currentPage"></strong> sur <strong x-text="totalPages"></strong>
            </span>
            <button
                type="button"
                class="btn btn--secondary btn--sm"
                @click="nextPage"
                :disabled="currentPage === totalPages"
                style="padding: 4px 10px; font-size: var(--font-sm);"
            >
                Suivant ›
            </button>
        </div>
    </div>
</div>
