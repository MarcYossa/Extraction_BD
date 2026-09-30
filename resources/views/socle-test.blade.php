<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Extraction BD - Test du socle</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 1000px; color: #1f2937; padding: 0 1rem; }
        h1 { font-size: 1.4rem; }
        form { display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; margin: 1rem 0; }
        label { display: flex; flex-direction: column; font-size: .85rem; gap: .25rem; }
        input, select, button { padding: .45rem .6rem; font-size: .95rem; }
        button { cursor: pointer; }
        table { border-collapse: collapse; width: 100%; font-size: .9rem; }
        th, td { border: 1px solid #d1d5db; padding: .35rem .5rem; text-align: left; }
        th { background: #f3f4f6; }
        .info { background: #ecfdf5; border: 1px solid #a7f3d0; padding: .6rem .8rem; margin: 1rem 0; }
        .warn { background: #fffbeb; border: 1px solid #fde68a; padding: .6rem .8rem; margin: 1rem 0; }
        .err  { background: #fef2f2; border: 1px solid #fecaca; padding: .6rem .8rem; margin: 1rem 0; }
    </style>
</head>
<body>
    <h1>Test du socle : comptes ouverts par agence</h1>
    <p>Page temporaire de validation (environnement de developpement uniquement).</p>

    <form method="get" action="{{ url('/socle-test') }}">
        <label>Agence
            <select name="agence">
                @foreach ($agences as $a)
                    <option value="{{ $a->agence_id }}" @selected((string) $params['agence'] === (string) $a->agence_id)>{{ $a->nom_agence }}</option>
                @endforeach
            </select>
        </label>
        <label>Date de debut
            <input type="text" name="date_debut" value="{{ $params['date_debut'] }}" placeholder="AAAA-MM-JJ">
        </label>
        <label>Date de fin
            <input type="text" name="date_fin" value="{{ $params['date_fin'] }}" placeholder="AAAA-MM-JJ">
        </label>
        <label>Statut
            <select name="statut">
                @foreach (['OUVERT', 'BLOQUE', 'CLOTURE'] as $s)
                    <option value="{{ $s }}" @selected($params['statut'] === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit">Executer</button>
    </form>

    @if ($errors)
        <div class="err">
            <strong>Parametres invalides :</strong>
            <ul>@foreach ($errors as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($fail)
        <div class="err">{{ $fail }}</div>
    @endif

    @if ($result)
        <div class="info">
            {{ count($result['rows']) }} ligne(s) affichee(s) en {{ $result['duration_ms'] }} ms
            (plafond : {{ $result['max_rows'] }}).
            <a href="{{ url('/socle-test/csv') }}?{{ $query }}">Exporter en CSV</a>
        </div>

        @if ($result['truncated'])
            <div class="warn">Resultat tronque au plafond de {{ $result['max_rows'] }} lignes. Affinez les criteres.</div>
        @endif

        @if (count($result['rows']))
            <table>
                <thead><tr>@foreach ($result['columns'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($result['rows'] as $row)
                    <tr>@foreach ($row as $v)<td>{{ $v }}</td>@endforeach</tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p>Aucun resultat pour ces criteres.</p>
        @endif
    @endif
</body>
</html>
