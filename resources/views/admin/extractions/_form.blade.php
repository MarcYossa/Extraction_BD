{{-- Formulaire partagé S04 (Création / Modification d'une extraction) --}}

@php
    $sqlInitial = is_array($extraction['requete_sql'] ?? '') 
        ? implode("\n", $extraction['requete_sql']) 
        : ($extraction['requete_sql'] ?? '');
    $paramsInitial = $extraction['parametres'] ?? [];
    $colsInitial   = $extraction['colonnes'] ?? [];
@endphp

<div x-data="extractionFormBuilder({
    sql: {{ json_encode($sqlInitial) }},
    parametres: {{ json_encode($paramsInitial) }},
    colonnes: {{ json_encode($colsInitial) }}
})">

    {{-- Formulaire HTTP principal --}}
    <form method="POST" action="{{ $formAction }}" id="form-extraction">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        {{-- Alerte d'avertissement extraction active (D11) --}}
        @if($isCurrentlyActive ?? false)
            <div class="alert alert--warning mb-6" id="alert-active-warning">
                <span style="width:20px;height:20px;" class="icon alert__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </span>
                <div class="alert__content">
                    <p class="font-medium">Attention : cette extraction est actuellement Active dans le catalogue métier.</p>
                    <p class="text-sm">Si vous enregistrez des modifications, elle sera retirée du catalogue métier et repassera en <strong>Brouillon</strong> (version {{ ($extraction['version'] ?? 1) + 1 }}) jusqu'à une nouvelle publication.</p>
                </div>
            </div>
        @endif

        {{-- Erreurs de validation serveur --}}
        @if ($errors->any())
            <div class="alert alert--error mb-6" id="alert-form-errors">
                <span style="width:20px;height:20px;" class="icon alert__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </span>
                <div class="alert__content">
                    <p class="font-medium">Veuillez corriger les erreurs ci-dessous :</p>
                    <ul class="list-disc pl-4 text-sm mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════════════════
           SECTION 01 – Informations générales
        ══════════════════════════════════════════════════════════════════════ --}}
        <div class="form-section" id="section-01-infos">
            <div class="form-section__header">
                <div class="form-section__badge-title">
                    <span class="form-section__number">01</span>
                    <h2 class="form-section__title">Informations générales</h2>
                </div>
                <p class="form-section__subtitle">Les informations visibles dans le catalogue métier.</p>
            </div>

            <div class="form-section__body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Code --}}
                    <div>
                        <label for="input-code" class="label">Code <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            name="code"
                            id="input-code"
                            class="input @error('code') input--error @enderror"
                            value="{{ old('code', $extraction['code'] ?? '') }}"
                            @if($isEdit) readonly style="background-color:var(--color-bg-body); cursor:not-allowed;" @endif
                            required
                        />
                        @if($isEdit)
                            <p class="text-xs text-muted mt-1">Le code définitif est non modifiable.</p>
                        @else
                            <p class="text-xs text-muted mt-1">3 lettres majuscules + tiret bas + 3 chiffres (ex: CPT_006).</p>
                        @endif
                        @error('code') <p class="form-error-msg">{{ $message }}</p> @enderror
                    </div>

                    {{-- Titre --}}
                    <div>
                        <label for="input-titre" class="label">Titre <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            name="titre"
                            id="input-titre"
                            class="input @error('titre') input--error @enderror"
                            placeholder="ex. Soldes des comptes par agence"
                            value="{{ old('titre', $extraction['titre'] ?? '') }}"
                            required
                        />
                        @error('titre') <p class="form-error-msg">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Description et Statut --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="input-description" class="label">Description <span class="text-danger">*</span></label>
                        <textarea
                            name="description"
                            id="input-description"
                            rows="3"
                            class="input @error('description') input--error @enderror"
                            placeholder="Décrivez l'objectif et le contenu de cette extraction."
                            required
                        >{{ old('description', $extraction['description'] ?? '') }}</textarea>
                        @error('description') <p class="form-error-msg">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="select-statut" class="label">Statut</label>
                        <select id="select-statut" class="input" disabled style="background-color:var(--color-bg-body); cursor:not-allowed;">
                            <option selected>
                                {{ ucfirst($extraction['statut'] ?? 'brouillon') }}
                            </option>
                        </select>
                        <p class="text-xs text-muted mt-1">Lecture seule. Le statut évolue via la liste (D22) et la publication.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════════
           SECTION 02 – Requête SQL
        ══════════════════════════════════════════════════════════════════════ --}}
        <div class="form-section" id="section-02-sql">
            <div class="form-section__header">
                <div class="form-section__badge-title">
                    <span class="form-section__number">02</span>
                    <h2 class="form-section__title">Requête SQL</h2>
                </div>
                <p class="form-section__subtitle">Utilisez uniquement des requêtes de lecture (SELECT).</p>
            </div>

            <div class="form-section__body">
                <div class="code-editor-wrapper">
                    <div class="code-editor-bar">
                        <span>requete.sql</span>
                        <span class="code-editor-bar__tag">SQL</span>
                    </div>
                    <div class="code-editor-container">
                        <textarea
                            name="requete_sql"
                            id="input-requete-sql"
                            class="code-editor-textarea"
                            x-model="sql"
                            placeholder="SELECT c.num_compte, c.solde FROM amp_test.compte c WHERE c.date_reference = :date_reference"
                            rows="6"
                            required
                        ></textarea>
                    </div>
                </div>

                {{-- Action de vérification SQL et message de retour --}}
                <div class="flex items-center gap-4 flex-wrap">
                    <button
                        type="button"
                        class="btn btn--outline"
                        id="btn-verifier-requete"
                        @click="verifierRequete()"
                        :disabled="verification.enCours"
                    >
                        <span x-show="!verification.enCours" style="width:16px;height:16px;" class="icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        </span>
                        <span x-show="verification.enCours" class="spinner" style="width:14px;height:14px;"></span>
                        Vérifier la requête
                    </button>

                    <div x-show="verification.valide === true" class="text-success text-sm flex items-center gap-1 font-medium" id="msg-sql-success">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span x-text="verification.message"></span>
                    </div>
                </div>

                {{-- Erreurs de vérification SQL AJAX --}}
                <div x-show="verification.valide === false" class="alert alert--error" id="msg-sql-errors">
                    <div class="alert__content text-sm">
                        <p class="font-medium">La requête ne peut pas être validée :</p>
                        <ul class="list-disc pl-4 mt-1">
                            <template x-for="(err, idx) in verification.erreurs" :key="idx">
                                <li x-text="err"></li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════════
           SECTION 03 – Paramètres
        ══════════════════════════════════════════════════════════════════════ --}}
        <div class="form-section" id="section-03-parametres">
            <div class="form-section__header">
                <div class="form-section__badge-title">
                    <span class="form-section__number">03</span>
                    <h2 class="form-section__title">Paramètres</h2>
                </div>
                <p class="form-section__subtitle">Les champs qui seront demandés à l'utilisateur métier.</p>
            </div>

            <div class="form-section__body">
                <template x-for="(param, index) in parametres" :key="index">
                    <div class="param-card" :id="'param-row-' + index">
                        <div class="param-card__main">
                            {{-- Poignée et boutons de déplacement --}}
                            <div class="flex items-center gap-1">
                                <span class="param-card__drag" title="Réorganiser">⋮⋮</span>
                                <div class="flex flex-col gap-0.5">
                                    <button type="button" class="btn btn--ghost btn--xs" @click="monterParametre(index)" :disabled="index === 0" title="Monter">▲</button>
                                    <button type="button" class="btn btn--ghost btn--xs" @click="descendreParametre(index)" :disabled="index === parametres.length - 1" title="Descendre">▼</button>
                                </div>
                            </div>

                            {{-- Nom --}}
                            <div style="flex: 1; min-width: 140px;">
                                <label class="label text-xs">Nom (:marqueur)</label>
                                <input
                                    type="text"
                                    :name="'parametres[' + index + '][nom]'"
                                    x-model="param.nom"
                                    class="input input--sm"
                                    placeholder="ex. date_debut"
                                    required
                                />
                            </div>

                            {{-- Libellé --}}
                            <div style="flex: 1.5; min-width: 160px;">
                                <label class="label text-xs">Libellé</label>
                                <input
                                    type="text"
                                    :name="'parametres[' + index + '][libelle]'"
                                    x-model="param.libelle"
                                    class="input input--sm"
                                    placeholder="ex. Date de début"
                                    required
                                />
                            </div>

                            {{-- Type --}}
                            <div style="flex: 1; min-width: 130px;">
                                <label class="label text-xs">Type</label>
                                <select
                                    :name="'parametres[' + index + '][type]'"
                                    x-model="param.type"
                                    class="input input--sm"
                                >
                                    <option value="texte">Texte</option>
                                    <option value="nombre">Nombre</option>
                                    <option value="date">Date</option>
                                    <option value="liste">Liste déroulante</option>
                                    <option value="booleen">Interrupteur Oui/Non</option>
                                </select>
                            </div>

                            {{-- Case Obligatoire --}}
                            <div class="flex items-center gap-2 pt-4">
                                <label class="checkbox text-xs">
                                    <input
                                        type="checkbox"
                                        :name="'parametres[' + index + '][obligatoire]'"
                                        value="1"
                                        x-model="param.obligatoire"
                                    />
                                    <span>Obligatoire</span>
                                </label>
                            </div>

                            {{-- Bouton Supprimer --}}
                            <div class="pt-4">
                                <button type="button" class="btn btn--ghost btn--sm text-danger" @click="supprimerParametre(index)" title="Supprimer ce paramètre">
                                    ✕
                                </button>
                            </div>
                        </div>

                        {{-- Options dépliées selon le type choisi --}}
                        <div class="param-card__options" x-show="['texte', 'nombre', 'date', 'liste'].includes(param.type)">
                            {{-- Option Texte : Longueur max --}}
                            <div x-show="param.type === 'texte'" class="flex items-center gap-3">
                                <label class="label text-xs">Longueur maximale (caractères) :</label>
                                <input type="number" :name="'parametres[' + index + '][longueur_max]'" x-model="param.longueur_max" class="input input--sm w-28" min="1" max="1000" placeholder="ex. 100" />
                            </div>

                            {{-- Option Nombre : Min & Max --}}
                            <div x-show="param.type === 'nombre'" class="flex items-center gap-4 flex-wrap">
                                <div class="flex items-center gap-2">
                                    <label class="label text-xs">Valeur min :</label>
                                    <input type="number" :name="'parametres[' + index + '][min]'" x-model="param.min" class="input input--sm w-28" placeholder="ex. 0" />
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="label text-xs">Valeur max :</label>
                                    <input type="number" :name="'parametres[' + index + '][max]'" x-model="param.max" class="input input--sm w-28" placeholder="ex. 1000000" />
                                </div>
                            </div>

                            {{-- Option Date : Après ou égale à --}}
                            <div x-show="param.type === 'date'" class="flex items-center gap-3">
                                <label class="label text-xs">Après ou égale à (autre paramètre date) :</label>
                                <select :name="'parametres[' + index + '][apres_ou_egal]'" x-model="param.apres_ou_egal" class="input input--sm w-48">
                                    <option value="">— Aucun —</option>
                                    <template x-for="pOther in parametres" :key="pOther.nom">
                                        <template x-if="pOther.nom !== param.nom && pOther.type === 'date'">
                                            <option :value="pOther.nom" x-text="pOther.nom + ' (' + pOther.libelle + ')'"></option>
                                        </template>
                                    </template>
                                </select>
                            </div>

                            {{-- Option Liste : Source & Option Toutes --}}
                            <div x-show="param.type === 'liste'" class="flex flex-col gap-3">
                                <div class="flex items-center gap-4">
                                    <label class="label text-xs">Source des options :</label>
                                    <label class="inline-flex items-center gap-1 text-xs">
                                        <input type="radio" :name="'parametres[' + index + '][source_type]'" value="fixe" x-model="param.source_type" /> Valeurs fixes
                                    </label>
                                    <label class="inline-flex items-center gap-1 text-xs">
                                        <input type="radio" :name="'parametres[' + index + '][source_type]'" value="requete" x-model="param.source_type" /> Requête SQL
                                    </label>
                                </div>

                                <div x-show="param.source_type !== 'requete'">
                                    <label class="label text-xs">Options fixes (une par ligne : <code>valeur | libellé</code>) :</label>
                                    <textarea :name="'parametres[' + index + '][options_fixes]'" x-model="param.options_fixes" rows="3" class="input input--sm text-xs font-mono" placeholder="ACTIF | Actif&#10;INACTIF | Inactif"></textarea>
                                </div>

                                <div x-show="param.source_type === 'requete'">
                                    <label class="label text-xs">Requête SQL de source (doit renvoyer <code>valeur</code> et <code>libelle</code>) :</label>
                                    <textarea :name="'parametres[' + index + '][source_requete]'" x-model="param.source_requete" rows="3" class="input input--sm text-xs font-mono" placeholder="SELECT agence_id AS valeur, nom_agence AS libelle FROM amp_test.agence ORDER BY nom_agence"></textarea>
                                </div>

                                <div>
                                    <label class="label text-xs">Libellé de l'option « Toutes » (optionnel) :</label>
                                    <input type="text" :name="'parametres[' + index + '][option_toutes]'" x-model="param.option_toutes" class="input input--sm w-64" placeholder="— Toutes les agences —" />
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="flex items-center gap-3 mt-2">
                    <button type="button" class="btn btn--outline btn--sm" id="btn-ajouter-parametre" @click="ajouterParametre()">
                        + Ajouter un paramètre
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" id="btn-detecter-parametres" @click="detecterParametresDepuisSql()">
                        Détecter depuis la requête
                    </button>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════════
           SECTION 04 – Colonnes du résultat (Spécification section 11)
        ══════════════════════════════════════════════════════════════════════ --}}
        <div class="form-section" id="section-04-colonnes">
            <div class="form-section__header">
                <div class="form-section__badge-title">
                    <span class="form-section__number">04</span>
                    <h2 class="form-section__title">Colonnes du résultat</h2>
                </div>
                <p class="form-section__subtitle">Configuration des titres et formats de présentation du tableau et du CSV.</p>
            </div>

            <div class="form-section__body">
                <table class="colonnes-table" id="table-colonnes">
                    <thead>
                        <tr>
                            <th>Clé SELECT</th>
                            <th>Libellé à l'affichage</th>
                            <th>Format de présentation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(col, index) in colonnes" :key="index">
                            <tr>
                                <td class="font-mono text-xs font-semibold" x-text="col.cle"></td>
                                <td>
                                    <input type="hidden" :name="'colonnes[' + index + '][cle]'" :value="col.cle" />
                                    <input type="text" :name="'colonnes[' + index + '][libelle]'" x-model="col.libelle" class="input input--sm" required />
                                </td>
                                <td>
                                    <select :name="'colonnes[' + index + '][format]'" x-model="col.format" class="input input--sm">
                                        <option value="texte">Texte</option>
                                        <option value="code">Puce code (monospace)</option>
                                        <option value="nombre">Nombre</option>
                                        <option value="montant">Montant (aligné à droite)</option>
                                        <option value="date">Date (JJ/MM/AAAA)</option>
                                        <option value="statut">Statut (point coloré)</option>
                                    </select>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="colonnes.length === 0">
                            <td colspan="3" class="text-center text-muted text-xs py-4">
                                Aucune colonne détectée. Entrez une requête SQL et cliquez sur « Vérifier la requête ».
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════════
           BARRE D'ACTIONS (Pied de page)
        ══════════════════════════════════════════════════════════════════════ --}}
        <input type="hidden" name="action" x-ref="actionInput" value="enregistrer" />

        <div class="form-actions-bar">
            <a href="{{ route('admin.extractions.index') }}" class="btn btn--outline" id="btn-annuler-form">
                Annuler
            </a>
            <button type="submit" name="action" value="enregistrer" class="btn btn--secondary" id="btn-enregistrer-brouillon" @click="$refs.actionInput.value = 'enregistrer'">
                Enregistrer en brouillon
            </button>
            <button type="button" class="btn btn--primary" id="btn-tester-avant-publication" @click="showModalTester = true">
                Tester avant publication ›
            </button>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════════
           S05 – MODALE « Tester cette extraction ? » (06-modale-tester.png)
        ══════════════════════════════════════════════════════════════════════ --}}
        <div 
            class="modal-backdrop" 
            x-show="showModalTester" 
            x-transition.opacity 
            x-cloak 
            style="display: flex;"
            @keydown.escape.window="showModalTester = false"
        >
            <div class="modal" @click.outside="showModalTester = false">
                <div class="modal__icon" style="background-color: #fef3c7; color: #d97706; font-size: 24px; font-weight: bold; border-radius: var(--radius-full); width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    !
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Tester cette extraction ?</h3>
                    <p class="modal__desc">
                        Les paramètres de l'extraction seront utilisés pour générer un aperçu limité des résultats.
                    </p>
                </div>
                <div class="modal__actions">
                    <button type="button" class="btn btn--outline" @click="showModalTester = false" id="btn-modal-annuler">
                        Annuler
                    </button>
                    <button type="button" class="btn btn--primary" @click="lancerTest()" id="btn-modal-lancer-test">
                        Lancer le test
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- Script Alpine.js du composant de construction du formulaire S04 --}}
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('extractionFormBuilder', (initialData) => ({
        showModalTester: false,
        sql: initialData.sql || '',
        parametres: (initialData.parametres || []).map(p => ({
            nom: p.nom || '',
            libelle: p.libelle || p.nom || '',
            type: p.type || 'texte',
            obligatoire: !!p.obligatoire,
            longueur_max: p.longueur_max || '',
            min: p.min !== undefined ? p.min : '',
            max: p.max !== undefined ? p.max : '',
            apres_ou_egal: p.apres_ou_egal || '',
            option_toutes: p.option_toutes || '',
            source_type: p.source ? p.source.type : 'fixe',
            options_fixes: (p.source && p.source.options) 
                ? p.source.options.map(o => (o.valeur + (o.libelle ? ' | ' + o.libelle : ''))).join("\n") 
                : '',
            source_requete: (p.source && p.source.requete_sql) 
                ? (Array.isArray(p.source.requete_sql) ? p.source.requete_sql.join("\n") : p.source.requete_sql) 
                : ''
        })),
        colonnes: initialData.colonnes || [],
        verification: {
            enCours: false,
            valide: null,
            message: '',
            erreurs: []
        },

        async verifierRequete() {
            this.verification.enCours = true;
            this.verification.valide = null;
            this.verification.erreurs = [];

            try {
                const response = await fetch('{{ route("admin.extractions.verifier") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        requete_sql: this.sql,
                        parametres: this.parametres
                    })
                });

                const data = await response.json();
                this.verification.valide = data.valide;
                this.verification.message = data.message || '';
                this.verification.erreurs = data.erreurs || [];

                if (data.valide && data.colonnes) {
                    this.syncColonnes(data.colonnes);
                }
            } catch (err) {
                this.verification.valide = false;
                this.verification.erreurs = ['Erreur lors de la communication avec le serveur.'];
            } finally {
                this.verification.enCours = false;
            }
        },

        syncColonnes(detectedCols) {
            const existingMap = {};
            this.colonnes.forEach(c => { existingMap[c.cle] = c; });

            this.colonnes = detectedCols.map(cle => ({
                cle: cle,
                libelle: existingMap[cle]?.libelle || (cle.charAt(0).toUpperCase() + cle.slice(1).replace(/_/g, ' ')),
                format: existingMap[cle]?.format || 'texte'
            }));
        },

        ajouterParametre() {
            this.parametres.push({
                nom: 'param_' + (this.parametres.length + 1),
                libelle: 'Nouveau paramètre',
                type: 'texte',
                obligatoire: false,
                longueur_max: '',
                min: '',
                max: '',
                apres_ou_egal: '',
                option_toutes: '',
                source_type: 'fixe',
                options_fixes: '',
                source_requete: ''
            });
        },

        supprimerParametre(index) {
            this.parametres.splice(index, 1);
        },

        monterParametre(index) {
            if (index > 0) {
                const item = this.parametres.splice(index, 1)[0];
                this.parametres.splice(index - 1, 0, item);
            }
        },

        descendreParametre(index) {
            if (index < this.parametres.length - 1) {
                const item = this.parametres.splice(index, 1)[0];
                this.parametres.splice(index + 1, 0, item);
            }
        },

        detecterParametresDepuisSql() {
            const matches = this.sql.match(/(?<!:):([a-zA-Z0-9_]+)/g);
            if (!matches) return;

            const detectedNoms = [...new Set(matches.map(m => m.substring(1)))];
            const existingNoms = this.parametres.map(p => p.nom);

            detectedNoms.forEach(nom => {
                if (!existingNoms.includes(nom)) {
                    this.parametres.push({
                        nom: nom,
                        libelle: nom.charAt(0).toUpperCase() + nom.slice(1).replace(/_/g, ' '),
                        type: nom.includes('date') ? 'date' : 'texte',
                        obligatoire: true,
                        longueur_max: '',
                        min: '',
                        max: '',
                        apres_ou_egal: '',
                        option_toutes: '',
                        source_type: 'fixe',
                        options_fixes: '',
                        source_requete: ''
                    });
                }
            });
        },

        lancerTest() {
            this.showModalTester = false;
            if (this.$refs && this.$refs.actionInput) {
                this.$refs.actionInput.value = 'tester';
            }
            const form = document.getElementById('form-extraction');
            if (form) {
                form.submit();
            }
        }
    }));
});
</script>
