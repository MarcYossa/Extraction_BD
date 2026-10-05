@extends('layouts.app', ['fakeUser' => ['prenom' => 'Amine', 'nom' => 'M.', 'role' => 'admin', 'initiales' => 'AM', 'role_label' => 'Administrateur']])

@section('title', 'Guide de Style (Design System)')

@section('content')
<div class="styleguide-container" style="display:flex; flex-direction:column; gap:var(--space-8); padding-bottom:var(--space-12);">

    {{-- En-tête --}}
    <x-page-header 
        overtitle="DEVELOPPEMENT LOCAL"
        title="Guide de Style & Design System"
        subtitle="Présentation et revue de tous les composants d'interface et leurs états."
    >
        <x-slot name="action">
            <x-button variant="secondary" icon="refresh-cw" onclick="window.location.reload()">Rafraîchir</x-button>
        </x-slot>
    </x-page-header>

    {{-- 1. Boutons --}}
    <section class="card" style="display:flex; flex-direction:column; gap:var(--space-4);">
        <h2 style="font-size:var(--font-lg); font-weight:var(--weight-semibold);">1. Boutons (x-button)</h2>
        
        <div style="display:flex; flex-direction:column; gap:var(--space-4);">
            <div>
                <h3 style="font-size:var(--font-sm); font-weight:var(--weight-medium); margin-bottom:var(--space-2); color:var(--color-text-muted);">Variantes principales</h3>
                <div style="display:flex; flex-wrap:wrap; gap:var(--space-3); align-items:center;">
                    <x-button variant="primary">Bouton primaire</x-button>
                    <x-button variant="secondary">Bouton secondaire</x-button>
                    <x-button variant="link">Bouton lien</x-button>
                </div>
            </div>

            <div>
                <h3 style="font-size:var(--font-sm); font-weight:var(--weight-medium); margin-bottom:var(--space-2); color:var(--color-text-muted);">Avec icônes</h3>
                <div style="display:flex; flex-wrap:wrap; gap:var(--space-3); align-items:center;">
                    <x-button variant="primary" icon="plus">Créer une extraction</x-button>
                    <x-button variant="secondary" icon="download">Exporter CSV</x-button>
                    <x-button variant="secondary" icon-right="chevron-right">Suivant</x-button>
                    <x-button variant="link" icon="arrow-left">Retour</x-button>
                </div>
            </div>

            <div>
                <h3 style="font-size:var(--font-sm); font-weight:var(--weight-medium); margin-bottom:var(--space-2); color:var(--color-text-muted);">Tailles</h3>
                <div style="display:flex; flex-wrap:wrap; gap:var(--space-3); align-items:center;">
                    <x-button size="sm" icon="plus">Petit (sm)</x-button>
                    <x-button size="md" icon="plus">Moyen (md)</x-button>
                    <x-button size="lg" icon="plus">Grand (lg)</x-button>
                </div>
            </div>

            <div>
                <h3 style="font-size:var(--font-sm); font-weight:var(--weight-medium); margin-bottom:var(--space-2); color:var(--color-text-muted);">États : Désactivé & Loading</h3>
                <div style="display:flex; flex-wrap:wrap; gap:var(--space-3); align-items:center;">
                    <x-button variant="primary" disabled>Désactivé</x-button>
                    <x-button variant="secondary" disabled>Désactivé</x-button>
                    <x-button variant="primary" loading>Chargement en cours...</x-button>
                </div>
            </div>
        </div>
    </section>

    {{-- 2. Champs de Formulaire --}}
    <section class="card" style="display:flex; flex-direction:column; gap:var(--space-4);">
        <h2 style="font-size:var(--font-lg); font-weight:var(--weight-semibold);">2. Champs de Formulaire</h2>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:var(--space-6);">
            
            <x-form-field label="Champ Texte Requis" for="field-req" required help="Ceci est un texte d'aide sous le champ.">
                <x-input id="field-req" name="field-req" placeholder="Saisir une valeur..." />
            </x-form-field>

            <x-form-field label="Champ avec Icône" for="field-icon">
                <x-input id="field-icon" name="field-icon" icon="search" placeholder="Rechercher une extraction..." />
            </x-form-field>

            <x-form-field label="Champ en Erreur" for="field-err" error="Le code saisi existe déjà dans le catalogue.">
                <x-input id="field-err" name="field-err" value="CLI_001" :error="true" />
            </x-form-field>

            <x-form-field label="Champ Désactivé" for="field-disabled">
                <x-input id="field-disabled" name="field-disabled" value="Valeur verrouillée" disabled />
            </x-form-field>

            <x-form-field label="Liste déroulante (Select)" for="field-select">
                <x-select id="field-select" name="field-select">
                    <option value="1">Option 1 - Agence Douala Principal</option>
                    <option value="2">Option 2 - Agence Yaoundé Centre</option>
                    <option value="3">Option 3 - Agence Bafoussam</option>
                </x-select>
            </x-form-field>

            <x-form-field label="Zone de Texte (Textarea)" for="field-area" help="Description détaillée de l'extraction.">
                <x-textarea id="field-area" name="field-area" rows="3" placeholder="Saisir la description..."></x-textarea>
            </x-form-field>

        </div>

        <div style="display:flex; gap:var(--space-8); flex-wrap:wrap; margin-top:var(--space-4); align-items:center;">
            <x-toggle name="is_active" :checked="true" label="Extraction Active (Oui / Non)" id="toggle-1" />
            <x-toggle name="is_draft" :checked="false" label="Mode Brouillon" id="toggle-2" />
            <x-checkbox name="confirm" :checked="true" label="J'accepte les conditions d'exécution" id="check-1" />
        </div>
    </section>

    {{-- 3. Badges, Chips et Avatars --}}
    <section class="card" style="display:flex; flex-direction:column; gap:var(--space-4);">
        <h2 style="font-size:var(--font-lg); font-weight:var(--weight-semibold);">3. Badges, Chips & Avatars</h2>

        <div style="display:flex; flex-wrap:wrap; gap:var(--space-6); align-items:center;">
            <div>
                <h3 style="font-size:var(--font-sm); font-weight:var(--weight-medium); margin-bottom:var(--space-2); color:var(--color-text-muted);">Badges Statut</h3>
                <div style="display:flex; gap:var(--space-2); align-items:center;">
                    <x-badge-statut statut="active" />
                    <x-badge-statut statut="brouillon" />
                    <x-badge-statut statut="inactive" />
                    <x-badge-statut statut="test-reussi" />
                </div>
            </div>

            <div>
                <h3 style="font-size:var(--font-sm); font-weight:var(--weight-medium); margin-bottom:var(--space-2); color:var(--color-text-muted);">Chips Code Extraction</h3>
                <div style="display:flex; gap:var(--space-2); align-items:center;">
                    <x-chip-code code="CLI_001" />
                    <x-chip-code code="CPT_005" />
                    <x-chip-code code="ENG_012" />
                </div>
            </div>

            <div>
                <h3 style="font-size:var(--font-sm); font-weight:var(--weight-medium); margin-bottom:var(--space-2); color:var(--color-text-muted);">Avatars</h3>
                <div style="display:flex; gap:var(--space-3); align-items:center;">
                    <x-avatar initials="AM" size="sm" />
                    <x-avatar initials="AM" size="md" />
                    <x-avatar initials="AM" size="lg" />
                </div>
            </div>
        </div>
    </section>

    {{-- 4. Alertes --}}
    <section class="card" style="display:flex; flex-direction:column; gap:var(--space-4);">
        <h2 style="font-size:var(--font-lg); font-weight:var(--weight-semibold);">4. Alertes (x-alert)</h2>

        <div style="display:flex; flex-direction:column; gap:var(--space-3);">
            <x-alert type="success" title="Extraction enregistrée avec succès">
                La requête SQL a été validée et enregistrée en mode brouillon.
            </x-alert>
            <x-alert type="error" title="Erreur lors de la validation SQL">
                La requête contient un mot-clé interdit (DROP TABLE).
            </x-alert>
            <x-alert type="warning" title="Attention au plafond d'affichage">
                L'extraction retournera au maximum 5 000 lignes conformément aux règles de gestion.
            </x-alert>
            <x-alert type="info" title="Information système">
                Une maintenance planifiée aura lieu ce samedi à 22h.
            </x-alert>
        </div>
    </section>

    {{-- 5. Bandeau Paramètres & Éditeur SQL --}}
    <section class="card" style="display:flex; flex-direction:column; gap:var(--space-4);">
        <h2 style="font-size:var(--font-lg); font-weight:var(--weight-semibold);">5. Composants Métier (Éditeur SQL & Bandeau Paramètres)</h2>

        <div>
            <h3 style="font-size:var(--font-sm); font-weight:var(--weight-medium); margin-bottom:var(--space-2); color:var(--color-text-muted);">Bandeau « Paramètres utilisés » (x-stat-bandeau)</h3>
            <x-stat-bandeau :params="[
                'Code Agence' => '00001 - Douala Principal',
                'Date de début' => '01/01/2026',
                'Date de fin' => '31/01/2026',
                'Statut Compte' => 'Ouvert'
            ]" />
        </div>

        <div>
            <h3 style="font-size:var(--font-sm); font-weight:var(--weight-medium); margin-bottom:var(--space-2); color:var(--color-text-muted);">Éditeur SQL (x-code-editor) - Affichage</h3>
            <x-code-editor mode="display" value="SELECT age, ncp, clt, nom, prev FROM bkcom WHERE age = :p_agence AND dde >= :p_date_debut ORDER BY ncp ASC" />
        </div>
    </section>

    {{-- 6. Tableau de Données --}}
    <section class="card" style="display:flex; flex-direction:column; gap:var(--space-4);">
        <h2 style="font-size:var(--font-lg); font-weight:var(--weight-semibold);">6. Tableau de Données (x-data-table)</h2>

        @php
            $sampleColumns = [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'titre', 'label' => 'Titre de l\'extraction'],
                ['key' => 'categorie', 'label' => 'Catégorie'],
                ['key' => 'statut', 'label' => 'Statut'],
                ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-right'],
            ];
            $sampleRows = [
                [
                    'code' => 'CLI_001',
                    'titre' => 'Liste des comptes clients ouverts sur la période',
                    'categorie' => 'Clientèle',
                    'statut' => '<span class="badge badge--active"><span class="badge__dot"></span>Active</span>',
                    'actions' => '<button class="btn btn--secondary btn--sm">Consulter</button>',
                ],
                [
                    'code' => 'CPT_005',
                    'titre' => 'Solde des comptes courants par agence',
                    'categorie' => 'Comptabilité',
                    'statut' => '<span class="badge badge--brouillon"><span class="badge__dot"></span>Brouillon</span>',
                    'actions' => '<button class="btn btn--secondary btn--sm">Modifier</button>',
                ],
            ];
        @endphp

        <x-data-table :columns="$sampleColumns" :rows="$sampleRows" count="2 / 2 extractions" />
    </section>

    {{-- 7. Modale Alpine.js --}}
    <section class="card" style="display:flex; flex-direction:column; gap:var(--space-4);" x-data>
        <h2 style="font-size:var(--font-lg); font-weight:var(--weight-semibold);">7. Modale de Confirmation (x-modal)</h2>

        <div>
            <x-button variant="primary" icon="play" @click="$dispatch('open-modal', 'modal-demo')">Ouvrir la Modale Démo</x-button>
        </div>

        <x-modal id="modal-demo">
            <x-slot name="icon">
                <x-icon name="alert-triangle" size="24" style="color:var(--color-warning);" />
            </x-slot>
            <x-slot name="title">Confirmer la publication ?</x-slot>
            <x-slot name="description">
                Cette extraction sera immédiatement rendue accessible à l'ensemble des utilisateurs métier du groupe BCPME.
            </x-slot>
            <x-slot name="actions">
                <x-button variant="secondary" @click="$dispatch('close-modal', 'modal-demo')">Annuler</x-button>
                <x-button variant="primary" @click="$dispatch('close-modal', 'modal-demo')">Publier l'extraction</x-button>
            </x-slot>
        </x-modal>
    </section>

    {{-- 8. Grille de toutes les Icônes --}}
    <section class="card" style="display:flex; flex-direction:column; gap:var(--space-4);">
        <h2 style="font-size:var(--font-lg); font-weight:var(--weight-semibold);">8. Bibliothèque des 18 Icônes SVG (x-icon)</h2>

        @php
            $icons = [
                'grid', 'list', 'plus', 'settings', 'logout', 'search', 'edit',
                'chevron-up', 'chevron-down', 'chevron-left', 'chevron-right',
                'x', 'database', 'play', 'download', 'check', 'alert-triangle',
                'grip-vertical', 'eye'
            ];
        @endphp

        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap:var(--space-3);">
            @foreach ($icons as $iconName)
                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:var(--space-3); border:1px solid var(--color-border-subtle); border-radius:var(--radius-md); gap:var(--space-2); text-align:center;">
                    <x-icon :name="$iconName" size="22" style="color:var(--color-text-main);" />
                    <span style="font-size:var(--font-xs); font-family:var(--font-mono); color:var(--color-text-muted);">{{ $iconName }}</span>
                </div>
            @endforeach
        </div>
    </section>

</div>
@endsection
