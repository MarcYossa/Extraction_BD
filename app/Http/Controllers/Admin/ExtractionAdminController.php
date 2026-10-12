<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\SelectColumnParser;
use App\Services\Extraction\SqlGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écran S03 (Liste) & Écran S04 (Création / Modification d'une extraction).
 */
class ExtractionAdminController extends Controller
{
    public function __construct(protected CatalogueRepository $repository) {}

    /**
     * GET /admin/extractions
     * Paramètres GET : q (recherche code/titre) et statut (filtre de statut).
     */
    public function index(Request $request): View
    {
        $q      = trim((string) $request->query('q', ''));
        $statut = trim((string) $request->query('statut', ''));

        $extractions = $this->repository->all();

        if ($q !== '') {
            $extractions = array_values(array_filter($extractions, function (array $ext) use ($q): bool {
                return str_contains(strtoupper($ext['code'] ?? ''), strtoupper($q))
                    || str_contains(strtoupper($ext['titre'] ?? ''), strtoupper($q));
            }));
        }

        if ($statut !== '' && strtolower($statut) !== 'tous') {
            $extractions = array_values(array_filter($extractions, function (array $ext) use ($statut): bool {
                return strcasecmp($ext['statut'] ?? '', $statut) === 0;
            }));
        }

        usort($extractions, function (array $a, array $b): int {
            return strcmp($b['modifie_le'] ?? '', $a['modifie_le'] ?? '');
        });

        $total = count($this->repository->all());

        return view('admin.extractions.index', compact('extractions', 'q', 'statut', 'total'));
    }

    /**
     * GET /admin/extractions/create
     * Formulaire S04 en création.
     */
    public function create(): View
    {
        $codePropose = $this->proposerNouveauCode();
        $extraction = [
            'code'        => $codePropose,
            'titre'       => '',
            'description' => '',
            'statut'      => 'brouillon',
            'version'     => 1,
            'requete_sql' => ["SELECT c.numero_compte, c.solde_comptable\nFROM amplitude.comptes c\nWHERE c.date_reference = :date_reference"],
            'parametres'  => [
                [
                    'nom'         => 'date_reference',
                    'libelle'     => 'Date de référence',
                    'type'        => 'date',
                    'obligatoire' => true,
                ],
            ],
            'colonnes'    => [],
        ];

        $isEdit = false;

        return view('admin.extractions.create', compact('extraction', 'isEdit'));
    }

    /**
     * GET /admin/extractions/{code}/edit
     * Formulaire S04 en édition.
     */
    public function edit(string $code): View
    {
        $extraction = $this->repository->find($code);

        if (! $extraction) {
            abort(404);
        }

        $isEdit = true;
        $isCurrentlyActive = (strtolower($extraction['statut'] ?? '') === 'active');

        return view('admin.extractions.edit', compact('extraction', 'isEdit', 'isCurrentlyActive'));
    }

    /**
     * POST /admin/extractions
     * Enregistrement d'une nouvelle extraction (statut brouillon par défaut).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateForm($request, isEdit: false);

        $code = strtoupper(trim($validated['code']));

        // Vérifier l'unicité du code
        if ($this->repository->find($code)) {
            return back()->withErrors(['code' => "Le code '{$code}' existe déjà dans le catalogue."])->withInput();
        }

        $sqlLines = $this->formatSqlLines($request->input('requete_sql', ''));
        $parametres = $this->cleanParametres($request->input('parametres', []));
        $colonnes   = $this->cleanColonnes($request->input('colonnes', []), $sqlLines);

        $extraction = [
            'code'         => $code,
            'titre'        => trim($validated['titre']),
            'description'  => trim($validated['description']),
            'statut'       => 'brouillon', // Toujours brouillon en création (D22)
            'version'      => 1,
            'requete_sql'  => $sqlLines,
            'parametres'   => $parametres,
            'colonnes'     => $colonnes,
            'cree_le'      => now()->toIso8601String(),
            'cree_par'     => auth()->user()->identifiant,
            'modifie_le'   => now()->toIso8601String(),
            'modifie_par'  => auth()->user()->identifiant,
            'publie_le'    => null,
            'publie_par'   => null,
            'dernier_test' => null,
        ];

        $this->repository->save($extraction, auth()->user()->identifiant);

        if ($request->input('action') === 'tester') {
            return redirect()->route('admin.extractions.test', ['code' => $code]);
        }

        return redirect()->route('admin.extractions.index')
            ->with('success', "L'extraction {$code} a été créée avec succès (statut Brouillon).");
    }

    /**
     * PUT /admin/extractions/{code}
     * Modification d'une extraction existante.
     * Si l'extraction était active, elle repasse en brouillon avec version +1 (D11).
     */
    public function update(Request $request, string $code): RedirectResponse
    {
        $existing = $this->repository->find($code);

        if (! $existing) {
            abort(404);
        }

        $validated = $this->validateForm($request, isEdit: true);

        $sqlLines  = $this->formatSqlLines($request->input('requete_sql', ''));
        $parametres = $this->cleanParametres($request->input('parametres', []));
        $colonnes   = $this->cleanColonnes($request->input('colonnes', []), $sqlLines);

        $wasActive = (strtolower($existing['statut'] ?? '') === 'active');
        $newVersion = ((int) ($existing['version'] ?? 1)) + 1;

        $extraction = array_merge($existing, [
            'titre'        => trim($validated['titre']),
            'description'  => trim($validated['description']),
            'statut'       => 'brouillon', // Rétrogradation automatique en brouillon si active (D11)
            'version'      => $newVersion,
            'requete_sql'  => $sqlLines,
            'parametres'   => $parametres,
            'colonnes'     => $colonnes,
            'modifie_le'   => now()->toIso8601String(),
            'modifie_par'  => auth()->user()->identifiant,
            'dernier_test' => null, // Remis à null car la version a changé
        ]);

        $this->repository->save($extraction, auth()->user()->identifiant);

        $message = "L'extraction {$code} a été mise à jour (version {$newVersion}).";
        if ($wasActive) {
            $message = "L'extraction {$code} était active. Suite à vos modifications, elle est repassée en brouillon (version {$newVersion}).";
        }

        if ($request->input('action') === 'tester') {
            return redirect()->route('admin.extractions.test', ['code' => $code])
                ->with('info', $message);
        }

        return redirect()->route('admin.extractions.index')
            ->with('success', $message);
    }

    /**
     * POST /admin/extractions/verifier
     * Route JSON d'analyse statique de la requête SQL sans exécution Oracle.
     */
    public function verifier(Request $request): JsonResponse
    {
        $sqlInput = $request->input('requete_sql', '');
        $sqlText = is_array($sqlInput) ? implode("\n", $sqlInput) : (string) $sqlInput;
        $sqlText = trim($sqlText);

        if ($sqlText === '') {
            return response()->json([
                'valide'  => false,
                'erreurs' => ['La requête SQL ne peut pas être vide.'],
            ]);
        }

        $erreurs = [];

        // 1. Contrôle par SqlGuard
        try {
            (new SqlGuard())->assertReadOnly($sqlText);
        } catch (\Throwable $e) {
            $erreurs[] = $e->getMessage();
        }

        // 2. Extraire les marqueurs :nom du SQL
        preg_match_all('/(?<!:):([a-zA-Z0-9_]+)/', $sqlText, $matches);
        $paramsDansSql = array_values(array_unique($matches[1] ?? []));

        // Paramètres transmis par le formulaire
        $paramsDeclarés = $request->input('parametres', []);
        $nomsDeclarés = [];
        if (is_array($paramsDeclarés)) {
            foreach ($paramsDeclarés as $p) {
                if (! empty($p['nom'])) {
                    $nomsDeclarés[] = trim($p['nom']);
                }
            }
        }
        $nomsDeclarés = array_values(array_unique($nomsDeclarés));

        // Détecter :noms dans SQL non déclarés
        foreach ($paramsDansSql as $pName) {
            if (! in_array($pName, $nomsDeclarés, true)) {
                $erreurs[] = "Le paramètre :{$pName} est utilisé dans la requête SQL mais n'est pas déclaré.";
            }
        }

        // Détecter paramètres déclarés non utilisés dans SQL
        foreach ($nomsDeclarés as $pName) {
            if (! in_array($pName, $paramsDansSql, true)) {
                $erreurs[] = "Le paramètre :{$pName} est déclaré dans la liste mais n'est pas utilisé dans la requête SQL.";
            }
        }

        // 3. Détecter et analyser les colonnes du SELECT
        $colonnes = SelectColumnParser::parseColumns($sqlText);
        if (empty($colonnes)) {
            $erreurs[] = "Impossible de déterminer les colonnes du SELECT.";
        } else {
            $counts = array_count_values($colonnes);
            foreach ($counts as $col => $cnt) {
                if ($cnt > 1) {
                    $erreurs[] = "Doublon dans les colonnes du SELECT : la colonne '{$col}' apparaît plusieurs fois. Utilisez des alias 'AS' uniques.";
                }
            }
        }

        if (! empty($erreurs)) {
            return response()->json([
                'valide'  => false,
                'erreurs' => array_values(array_unique($erreurs)),
            ]);
        }

        return response()->json([
            'valide'              => true,
            'message'             => 'La requête respecte les règles de lecture.',
            'colonnes'            => $colonnes,
            'parametres_detectes' => $paramsDansSql,
        ]);
    }

    /**
     * PATCH /admin/extractions/{code}/statut (D22 – cycle de vie)
     */
    public function toggleStatut(string $code): RedirectResponse
    {
        $extraction = $this->repository->find($code);

        if (! $extraction) {
            abort(404);
        }

        $statutActuel = strtolower($extraction['statut'] ?? 'brouillon');

        if ($statutActuel === 'active') {
            $extraction['statut'] = 'inactive';
            $message = "L'extraction {$code} a été désactivée.";
        } elseif ($statutActuel === 'inactive') {
            $extraction['statut'] = 'active';
            $message = "L'extraction {$code} a été réactivée.";
        } else {
            return redirect()->route('admin.extractions.index')
                ->with('error', "Le statut d'un brouillon ne peut pas être modifié depuis la liste.");
        }

        $this->repository->save($extraction, auth()->user()->identifiant);

        return redirect()->route('admin.extractions.index')
            ->with('success', $message);
    }

    /**
     * Propose un code automatique d'extraction (ex: CPT_006).
     */
    protected function proposerNouveauCode(): string
    {
        $extractions = $this->repository->all();
        if (empty($extractions)) {
            return 'CPT_001';
        }

        // Trouver la dernière extraction modifiée pour déduire le préfixe courant
        $sorted = $extractions;
        usort($sorted, fn ($a, $b) => strcmp($b['modifie_le'] ?? '', $a['modifie_le'] ?? ''));
        $dernierCode = $sorted[0]['code'] ?? 'CPT_001';

        $parts = explode('_', $dernierCode);
        $prefixe = (count($parts) >= 2 && strlen($parts[0]) === 3) ? strtoupper($parts[0]) : 'CPT';

        // Trouver le plus grand numéro existant pour ce préfixe
        $maxNum = 0;
        foreach ($extractions as $ext) {
            $code = $ext['code'] ?? '';
            if (str_starts_with($code, $prefixe . '_')) {
                $numStr = substr($code, strlen($prefixe) + 1);
                if (is_numeric($numStr)) {
                    $maxNum = max($maxNum, (int) $numStr);
                }
            }
        }

        return sprintf('%s_%03d', $prefixe, $maxNum + 1);
    }

    /**
     * Validation des champs principaux du formulaire.
     */
    protected function validateForm(Request $request, bool $isEdit): array
    {
        $rules = [
            'titre'       => ['required', 'string', 'min:3', 'max:100'],
            'description' => ['required', 'string', 'min:3', 'max:300'],
        ];

        if (! $isEdit) {
            $rules['code'] = ['required', 'string', 'regex:/^[A-Z]{3}_[0-9]{3}$/'];
        }

        return $request->validate($rules, [
            'code.required'      => 'Le code de l\'extraction est obligatoire.',
            'code.regex'         => 'Le code doit comporter 3 lettres majuscules, un tiret bas et 3 chiffres (ex: CPT_006).',
            'titre.required'     => 'Le titre est obligatoire.',
            'titre.min'          => 'Le titre doit contenir au moins 3 caractères.',
            'titre.max'          => 'Le titre ne peut pas dépasser 100 caractères.',
            'description.required' => 'La description est obligatoire.',
            'description.min'    => 'La description doit contenir au moins 3 caractères.',
            'description.max'    => 'La description ne peut pas dépasser 300 caractères.',
        ]);
    }

    /**
     * Formatage de la requête SQL en tableau de lignes.
     */
    protected function formatSqlLines(string|array $input): array
    {
        if (is_array($input)) {
            $text = implode("\n", $input);
        } else {
            $text = (string) $input;
        }

        $lines = explode("\n", str_replace("\r\n", "\n", $text));
        return array_values(array_map('rtrim', $lines));
    }

    /**
     * Nettoyage et structuration des paramètres.
     */
    protected function cleanParametres(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $cleaned = [];
        foreach ($input as $p) {
            if (empty($p['nom'])) {
                continue;
            }

            $item = [
                'nom'         => trim($p['nom']),
                'libelle'     => trim($p['libelle'] ?? $p['nom']),
                'type'        => strtolower(trim($p['type'] ?? 'texte')),
                'obligatoire' => ! empty($p['obligatoire']),
            ];

            if (isset($p['defaut']) && $p['defaut'] !== '') {
                $item['defaut'] = $p['defaut'];
            }

            // Options selon le type
            if ($item['type'] === 'texte' && ! empty($p['longueur_max'])) {
                $item['longueur_max'] = (int) $p['longueur_max'];
            } elseif ($item['type'] === 'nombre') {
                if (isset($p['min']) && $p['min'] !== '') {
                    $item['min'] = (float) $p['min'];
                }
                if (isset($p['max']) && $p['max'] !== '') {
                    $item['max'] = (float) $p['max'];
                }
            } elseif ($item['type'] === 'date' && ! empty($p['apres_ou_egal'])) {
                $item['apres_ou_egal'] = trim($p['apres_ou_egal']);
            } elseif ($item['type'] === 'liste') {
                if (! empty($p['option_toutes'])) {
                    $item['option_toutes'] = trim($p['option_toutes']);
                }
                $sourceType = $p['source_type'] ?? 'fixe';
                if ($sourceType === 'requete' && ! empty($p['source_requete'])) {
                    $item['source'] = [
                        'type'        => 'requete',
                        'requete_sql' => $this->formatSqlLines($p['source_requete']),
                    ];
                } else {
                    $optionsFixes = [];
                    if (! empty($p['options_fixes'])) {
                        $lines = explode("\n", str_replace("\r\n", "\n", $p['options_fixes']));
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if ($line === '') continue;
                            $parts = explode('|', $line, 2);
                            $val = trim($parts[0]);
                            $lib = isset($parts[1]) ? trim($parts[1]) : $val;
                            $optionsFixes[] = ['valeur' => $val, 'libelle' => $lib];
                        }
                    }
                    $item['source'] = [
                        'type'    => 'fixe',
                        'options' => $optionsFixes,
                    ];
                }
            }

            $cleaned[] = $item;
        }

        return $cleaned;
    }

    /**
     * Nettoyage et structuration des colonnes de résultat.
     */
    protected function cleanColonnes(mixed $input, array $sqlLines): array
    {
        $detectedCols = SelectColumnParser::parseColumns($sqlLines);
        if (empty($detectedCols)) {
            return [];
        }

        $inputMap = [];
        if (is_array($input)) {
            foreach ($input as $col) {
                if (! empty($col['cle'])) {
                    $inputMap[strtolower(trim($col['cle']))] = $col;
                }
            }
        }

        $result = [];
        foreach ($detectedCols as $cle) {
            $userConfig = $inputMap[$cle] ?? [];
            $result[] = [
                'cle'     => $cle,
                'libelle' => trim($userConfig['libelle'] ?? ucfirst(str_replace('_', ' ', $cle))),
                'format'  => strtolower(trim($userConfig['format'] ?? 'texte')),
            ];
        }

        return $result;
    }
}
