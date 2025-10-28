
@extends('layouts.base')
@section('title','Créer une intervention')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/interventions.css') }}">

    <div class="app">
        <div class="card" style="max-width:860px;margin:0 auto">
            <div class="cardBody">
                @if ($errors->any())
                    <div style="background:#ffe3e6;border:1px solid #ffc2c8;border-radius:8px;padding:8px 10px;margin-bottom:10px;color:#7a1d27">
                        <strong>Erreurs :</strong>
                        <ul style="margin:6px 0 0 18px;padding:0">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ route('interventions.store') }}" class="b-form">
                    @csrf
                    <div class="b-grid">
                        <div class="b-field">
                            <label for="NumInt">Numéro intervention *</label>
                            <input id="NumInt" name="NumInt" required value="{{ old('NumInt') }}" placeholder="Ex: M34M-12345">
                            <small>Alphanumérique, tirets/underscores autorisés, unique.</small>
                        </div>

                        <div class="b-field">
                            <label for="Marque">Marque</label>
                            <input id="Marque" name="Marque" value="{{ old('Marque') ?? ($defaults['Marque'] ?? '') }}">
                        </div>

                        <div class="b-field">
                            <label for="VilleLivCli">Ville</label>
                            <input id="VilleLivCli" name="VilleLivCli" value="{{ old('VilleLivCli') ?? ($defaults['VilleLivCli'] ?? '') }}">
                        </div>

                        <div class="b-field">
                            <label for="CPLivCli">Code postal</label>
                            <input id="CPLivCli" name="CPLivCli" value="{{ old('CPLivCli') ?? ($defaults['CPLivCli'] ?? '') }}">
                        </div>

                        <div class="b-field">
                            <label for="DateIntPrevu">Date prévue</label>
                            <input type="date" id="DateIntPrevu" name="DateIntPrevu" value="{{ old('DateIntPrevu') ?? ($defaults['DateIntPrevu'] ?? '') }}">
                        </div>

                        <div class="b-field">
                            <label for="HeureIntPrevu">Heure prévue</label>
                            <input type="time" id="HeureIntPrevu" name="HeureIntPrevu" value="{{ old('HeureIntPrevu') ?? ($defaults['HeureIntPrevu'] ?? '') }}">
                        </div>

                        <div class="b-field b-full">
                            <label for="Commentaire">Commentaire</label>
                            <textarea id="Commentaire" name="Commentaire" rows="3" placeholder="Notes internes, contexte…">{{ old('Commentaire') ?? ($defaults['Commentaire'] ?? '') }}</textarea>
                        </div>

                        <div class="b-field">
                            <label><input type="checkbox" name="Urgent" value="1" {{ old('Urgent') ? 'checked' : '' }}> Marquer urgent</label>
                        </div>

                        <div class="b-field">
                            <label><input type="checkbox" name="Concerne" value="1" {{ old('Concerne') ? 'checked' : '' }}> M’assigner (VOUS)</label>
                            <small>Utilise votre CodeSal : <strong>{{ $codeSal ?: '—' }}</strong></small>
                        </div>
                    </div>

                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px">
                        <a class="btn btn-light" href="{{ route('interventions.show') }}">Annuler</a>
                        <button class="btn" type="submit">Créer l’intervention</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .b-form .b-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .b-form .b-full{grid-column:1 / -1}
        .b-form .b-field{display:flex;flex-direction:column;gap:6px}
        .b-form label{font-size:12px;color:var(--mut)}
        .b-form input,.b-form textarea{
            border:1px solid var(--line); border-radius:10px; background:#fff; color:var(--ink);
            padding:9px 11px; font:13.5px/1.3 Inter,system-ui;
        }
        .b-form input:focus,.b-form textarea:focus{outline:none;border-color:#cfe0ff;box-shadow:0 0 0 3px #e9f2ff}
        .b-form small{color:#8b93a7}
        @media (max-width:900px){ .b-form .b-grid{grid-template-columns:1fr} }
    </style>
@endsection
