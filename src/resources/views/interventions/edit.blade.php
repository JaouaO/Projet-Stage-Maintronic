@extends('layouts.base')
@section('title', 'Intervention')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        :root {
            --gap: 12px;
            --r: 8px;
            --bg: #f4f5f7;
            --panel: #fff;
            --ink: #222;
            --mut: #666;
            --line: #d6d9df;
            --head: #e9f2ff;
        }

        * {
            box-sizing: border-box
        }

        html, body {
            height: 100%
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font: 14px/1.4 system-ui, Segoe UI, Arial
        }

        /* ---- Layout plein écran (responsive) ---- */
        .app {
            min-height: 100vh;
            width: 100%;
            max-width: 1800px;
            margin: 0 auto;
            padding: 12px;
            display: grid;
            gap: var(--gap);
            grid-template-columns:minmax(260px, 26%) minmax(380px, 36%) minmax(520px, 38%);
            align-items: stretch;
        }

        .col {
            min-height: 0;
            display: flex;
            flex-direction: column;
            gap: var(--gap)
        }

        /* Cartes */
        .box {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--r, 8px);
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .box .head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: var(--head);
            border-bottom: 1px solid var(--line);
            border-top-left-radius: var(--r, 8px);
            border-top-right-radius: var(--r, 8px)
        }

        .box .body {
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-height: 0
        }

        /* Gauche : empêche l'explosion en dézoom */
        .left .hist {
            flex: 1 1 auto;
            min-height: 200px;
            max-height: 52vh
        }

        .left .mserv {
            flex: 0 0 auto
        }

        #noteInterne {
            height: 72px;
            overflow: auto
        }

        /* Table */
        .table {
            flex: 1;
            overflow: auto;
            margin: 0
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px
        }

        th, td {
            padding: 7px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top
        }

        th {
            background: #f7faff;
            position: sticky;
            top: 0;
            z-index: 1
        }

        td.status {
            text-align: center;
            width: 66px
        }

        .status input {
            transform: scale(1.05)
        }

        .note {
            color: var(--mut);
            font-size: 12px
        }

        /* Champs */
        .gridObj {
            display: grid;
            grid-template-columns:120px 1fr;
            gap: 8px;
            align-items: center
        }

        .grid2 {
            display: grid;
            grid-template-columns:120px 1fr;
            gap: 8px;
            align-items: center
        }

        .gridRow {
            display: grid;
            grid-template-columns:120px 1fr 110px 1fr;
            gap: 8px;
            align-items: center
        }

        input, select, textarea {
            width: 100%;
            padding: 7px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff
        }

        .ro {
            padding: 7px;
            border: 1px dashed var(--line);
            border-radius: 6px;
            background: #f9fafb;
            user-select: text;
            cursor: default;
            white-space: normal;
            overflow: hidden
        }

        /* Colonne Centre/Droite doivent pouvoir grandir */
        .center .box {
            flex: 1 1 auto
        }

        .right .box {
            flex: 1 1 auto
        }

        /* Pane affectation collé en haut de la colonne droite */
        .right .affectationSticky {
            position: sticky;
            top: 12px; /* même marge que ton padding extérieur */
            z-index: 3;
            background: var(--panel);
            padding-bottom: 8px;
            border-bottom: 1px solid var(--line);
        }

        /* Agenda */


        .agendaBox {
            overflow: auto; /* le scroll est ici */
            max-height: 60vh; /* valeur de secours ; JS ajuste précisément */
        }

        .agendaBox .head {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--head);
        }

        #agendaTech {
            display: grid;
            grid-template-columns:repeat(5, 1fr);
            gap: 8px
        }

        .agendaCard {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 8px;
            background: #fff
        }


        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
        }

        .cal-weekday {
            text-align: center;
            font-size: 12px;
            color: var(--mut);
        }

        .cal-cell {
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff;
            min-height: 42px;
            padding: 6px;
            font-size: 12px;
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            position: relative;
            cursor: pointer;
            user-select: none;
        }

        .cal-cell .d {
            font-variant-numeric: tabular-nums;
        }

        .cal-cell.muted {
            opacity: .55;
        }

        .cal-cell:hover {
            outline: 2px solid #cfe0ff;
        }

        .cal-cell .dot {
            position: absolute;
            left: 6px;
            bottom: 6px;
            width: 8px;
            height: 8px;
            border-radius: 999px;
            border: 1px solid rgba(0, 0, 0, .05);
        }

        #calWrap.collapsed #calGrid {
            display: none;
        }

        /* Boutons */
        .btn {
            border: 1px solid var(--line);
            background: #eef3ff;
            padding: 6px 12px;
            border-radius: 6px
        }

        .btn.ok {
            background: #e9f8ef;
            border-color: #cfead6;
            color: #0d6b2d;
            font-weight: 600
        }

        .affectationSticky thead {
            display: none
        }

        /* === Agenda technicien : compact === */
        .agendaBox .head {
            padding: 6px 8px
        }

        /* 8px -> 6px */
        .agendaBox .body {
            padding: 8px;
            gap: 8px
        }

        /* 10px -> 8px */
        .agendaBox .grid2 {
            grid-template-columns:96px 1fr; /* 120px -> 96px */
            gap: 6px; /* 8px -> 6px */
        }

        /* Mois + semaine + cases jour */
        .cal-grid {
            gap: 4px
        }

        /* 6px -> 4px */
        .cal-weekday {
            font-size: 11px
        }

        /* 12px -> 11px */
        .cal-cell {
            min-height: 34px; /* 42px -> 34px */
            padding: 4px; /* 6px -> 4px */
            font-size: 11px; /* 12px -> 11px */
        }

        /* Liste du jour un peu plus serrée */
        #calListTitle {
            margin: 4px 0;
            font-size: 12.5px
        }

        #calList .table thead th,
        #calList .table td {
            padding: 6px
        }

    </style>

    <div class="app">

        {{-- GAUCHE --}}
        <section class="col left">
            <div class="box hist">
                <div class="head"><strong>Historique (serveur)</strong><span class="note">résumé</span></div>
                <div class="body table">
                    <table>
                        <thead>
                        <tr>
                            <th style="width:150px">Date (srv)</th>
                            <th>Action / Commentaire</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($suivis as $s)
                            @php
                                $dateTxt='—';
                                if($s->CreatedAt){ try{$dt=\Carbon\Carbon::parse($s->CreatedAt); $dateTxt=$dt->format($dt->toTimeString()!=='00:00:00'?'d/m/Y H:i':'d/m/Y');}catch(\Exception $e){} }
                            @endphp
                            <tr>
                                <td>{{ $dateTxt }}</td>
                                <td>@if($s->CodeSalAuteur)
                                        <strong>{{ $s->CodeSalAuteur }}</strong> —
                                    @endif @if($s->Titre)
                                        <em>{{ $s->Titre }}</em> —
                                    @endif {{ $s->Texte }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td>—</td>
                                <td>Aucun suivi</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="box mserv">
                <div class="head"><strong>mServ</strong><span class="note">notes internes</span></div>
                <div class="body">
                    <div id="noteInterne"
                         data-update-url="{{ route('interventions.note.update', ['numInt'=>$interv->NumInt]) }}"
                         style="border:1px dashed var(--line);border-radius:6px;padding:8px;white-space:pre-wrap;background:#fff;cursor:text">
                        {{ $noteInterne }}
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <button id="btnEdit" class="btn" type="button">Modifier</button>
                        <button id="btnSave" class="btn" type="button" style="display:none;">Enregistrer</button>
                        <button id="btnCancel" class="btn" type="button" style="display:none;">Annuler</button>
                        <span id="noteCounter" style="margin-left:auto" class="note"></span>
                        <span id="noteStatus" class="note"></span>
                    </div>
                </div>
            </div>
        </section>

        {{-- CENTRE : Traitement du dossier --}}
        <section class="col center">
            <div class="box">
                <div class="head">
                    <strong>Traitement du dossier — {{ $interv->NumInt }}</strong>
                    <span class="note">{{ $data->NomSal ?? ($data->CodeSal ?? '—') }}</span>
                </div>
                <div class="body">
                    {{-- Objet sur toute la ligne --}}
                    <div class="gridObj">
                        <label>Objet</label>
                        <div class="ro">{{ $objetTrait ?: '—' }}</div>
                    </div>

                    {{-- Contact réel SEUL (ligne dédiée) --}}
                    <div class="grid2">
                        <label>Contact réel</label>
                        <input type="text" value="{{ $contactReel }}">
                    </div>

                    {{-- Date + Heure sur la même ligne --}}
                    <div class="gridRow">
                        <label>Date</label>
                        <div id="srvDateText" class="ro">—</div>
                        <label>Heure</label>
                        <div id="srvTimeText" class="ro">—</div>
                    </div>

                    {{-- Checklist TRAITEMENT (identique) --}}
                    <div class="table" style="margin-top:6px">
                        <table>
                            <thead>
                            <tr>
                                <th>Action de traitement</th>
                                <th style="width:66px">Statut</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php $trait = $traitementItems ?? []; @endphp
                            @forelse($trait as $it)
                                <tr>
                                    <td>{{ $it['label'] }}</td>
                                    <td class="status">
                                        <input type="checkbox"
                                               {{ !empty($it['checked']) ? 'checked' : '' }}
                                               data-group="TRAITEMENT" data-index="{{ $it['pos_index'] }}"
                                               data-code="{{ $it['code'] }}">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="note">Aucun item de traitement</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </section>

        {{-- DROITE : Affectation + Agenda du technicien (UNE SEULE CARTE) --}}
        <section class="col right">
            <div class="box">
                <div class="head"><strong>Affectation du dossier</strong></div>
                <div class="body">
                    {{-- Choix du type de réaffectation --}}

                    <!-- ✅ bloque sticky -->
                    <div class="affectationSticky">
                        <!-- Choix du type -->
                        <div class="grid2">
                            <label>Réaffecter à</label>
                            <div style="display:flex;gap:8px">
                                <label style="display:flex;align-items:center;gap:6px"><input type="radio"
                                                                                              name="reaType"
                                                                                              value="TECH" checked>
                                    Technicien</label>
                                <label style="display:flex;align-items:center;gap:6px"><input type="radio"
                                                                                              name="reaType"
                                                                                              value="SAL">
                                    Salarié</label>
                            </div>
                        </div>

                        {{-- Listes (une seule active à la fois) --}}
                        <div class="grid2" id="rowTech">
                            <label>Technicien</label>
                            <select id="selTech">
                                <option value="">— Sélectionner —</option>
                                @foreach($techniciens as $t)
                                    <option value="{{ $t->CodeSal }}">{{ $t->NomSal }} ({{ $t->CodeSal }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid2" id="rowSal" style="display:none">
                            <label>Salarié</label>
                            <select id="selSal">
                                <option value="">— Sélectionner —</option>
                                @foreach($salaries as $s)
                                    <option value="{{ $s->CodeSal }}">{{ $s->NomSal }} ({{ $s->CodeSal }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="gridRow">
                            <label>Date</label><input type="date" id="dtPrev">
                            <label>Heure</label><input type="time" id="tmPrev" step="300">
                        </div>

                        {{-- Étapes AFFECTATION en 2 colonnes --}}
                        <div class="table" style="margin-top:8px;">
                            <table>
                                <thead>
                                <tr>
                                    <th>Étapes de planification</th>
                                    <th style="width:66px">Statut</th>
                                    <th>Étapes de planification</th>
                                    <th style="width:66px">Statut</th>
                                </tr>
                                </thead>
                                <tbody>
                                @php $pairs = array_chunk(($affectationItems ?? []), 2); @endphp
                                @forelse($pairs as $pair)
                                    <tr>
                                        <td>{{ $pair[0]['label'] ?? '' }}</td>
                                        <td class="status">@if(isset($pair[0]))
                                                <input type="checkbox" data-group="AFFECTATION"
                                                       data-index="{{ $pair[0]['pos_index'] }}"
                                                       data-code="{{ $pair[0]['code'] }}">
                                            @endif</td>
                                        <td>{{ $pair[1]['label'] ?? '' }}</td>
                                        <td class="status">@if(isset($pair[1]))
                                                <input type="checkbox" data-group="AFFECTATION"
                                                       data-index="{{ $pair[1]['pos_index'] }}"
                                                       data-code="{{ $pair[1]['code'] }}">
                                            @endif</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="note">Aucun item d’affectation</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- ✅ Bouton placé juste sous les étapes, au-dessus de l’agenda --}}
                        <div style="display:flex;justify-content:flex-end;margin:8px 0 4px;">
                            <button id="btnPlanifier" class="btn ok" type="button">Valider le prochain rendez-vous
                            </button>
                        </div>
                    </div>
                    <!-- /affectationSticky -->

                    {{-- Agenda du technicien (affiché seulement si un tech est choisi) --}}
                    <div class="box agendaBox" id="agendaBox" style="display:flex;flex-direction:column">
                        <div class="head"><strong>Agenda technicien</strong><span class="note">vue mensuelle (Tous par défaut)</span>
                        </div>
                        <div class="body" style="gap:10px">

                            {{-- Sélecteur de technicien (par défaut: Tous) --}}
                            <div class="grid2">
                                <label>Technicien</label>
                                <select id="selModeTech">
                                    <option value="_ALL" selected>Tous les techniciens</option>
                                    @foreach($techniciens as $t)
                                        <option value="{{ $t->CodeSal }}">{{ $t->NomSal }} ({{ $t->CodeSal }})</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- En-tête calendrier (mois courant) --}}
                            <!-- ✅ wrapper du calendrier -->
                            <div id="calWrap">
                                <div id="calHead"
                                     style="display:flex;align-items:center;gap:8px;justify-content:space-between;">
                                    <button id="calPrev" class="btn" type="button" style="padding:4px 8px">◀</button>

                                    <!-- titre + toggle -->
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <div id="calTitle" style="font-weight:600"></div>
                                        <button id="calToggle" class="btn" type="button" style="padding:4px 8px"
                                                aria-expanded="true">▾ Mois
                                        </button>
                                    </div>

                                    <button id="calNext" class="btn" type="button" style="padding:4px 8px">▶</button>
                                </div>

                                {{-- Grille du mois (heat-map) --}}
                                <div id="calGrid" class="cal-grid"></div>

                                {{-- Liste du jour sélectionné --}}
                                <div id="calList" class="cal-list" style="display:none">
                                    <div id="calListHead"
                                         style="display:flex;align-items:center;justify-content:space-between;margin:6px 0">
                                        <div id="calListTitle" style="font-weight:600"></div>
                                        <button id="dayNext" class="btn" type="button" title="Jour suivant"
                                                style="padding:2px 8px">▶
                                        </button>
                                    </div>
                                    <div id="calListBody" class="table">
                                        <table>
                                            <thead>
                                            <tr>
                                                <th style="width:80px">Heure</th>
                                                <th style="width:80px">Tech</th>
                                                <th style="width:200px">Contact</th>
                                                <th>Commentaire</th>
                                            </tr>
                                            </thead>
                                            <tbody id="calListRows"></tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>
                            <!-- /calWrap -->
                        </div>
                    </div>


                </div>
            </div>
        </section>
    </div>


    <script>
        window.APP = {
          serverNow: "{{ $serverNow }}",
    sessionId: "{{ session('id') }}",
    apiPlanningRoute: "{{ route('api.planning.tech', ['codeTech' => '__X__']) }}",
    techs: @json($techniciens->pluck('CodeSal')->values()),
    names: @json($techniciens->mapWithKeys(fn($t)=>[$t->CodeSal=>$t->NomSal])),
  };
        {{-- expose l'id session pour la note interne --}}
        window.APP_SESSION_ID = "{{ session('id') }}";
    </script>
    <script src="{{ asset('js/intervention_note.js') }}" defer></script>
    <script src="{{ asset('js/intervention_edit.js') }}"></script>
@endsection
