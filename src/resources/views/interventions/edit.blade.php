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


        .agendaBox{
            overflow: auto;            /* le scroll est ici */
            max-height: 60vh;          /* valeur de secours ; JS ajuste précisément */
        }
        .agendaBox .head{
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

        .affectationSticky thead{display:none}

        /* === Agenda technicien : compact === */
        .agendaBox .head{padding:6px 8px}           /* 8px -> 6px */
        .agendaBox .body{padding:8px; gap:8px}      /* 10px -> 8px */
        .agendaBox .grid2{
            grid-template-columns:96px 1fr;           /* 120px -> 96px */
            gap:6px;                                  /* 8px -> 6px */
        }

        /* Mois + semaine + cases jour */
        .cal-grid{gap:4px}                          /* 6px -> 4px */
        .cal-weekday{font-size:11px}                /* 12px -> 11px */
        .cal-cell{
            min-height:34px;                          /* 42px -> 34px */
            padding:4px;                              /* 6px -> 4px */
            font-size:11px;                           /* 12px -> 11px */
        }

        /* Liste du jour un peu plus serrée */
        #calListTitle{margin:4px 0; font-size:12.5px}
        #calList .table thead th,
        #calList .table td{padding:6px}

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
                    <div class="box agendaBox" id="agendaBox" style="display:flex;flex-direction:column">                        <div class="head"><strong>Agenda technicien</strong><span class="note">vue mensuelle (Tous par défaut)</span>
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
                                    <div id="calListHead" style="display:flex;align-items:center;justify-content:space-between;margin:6px 0">
                                        <div id="calListTitle" style="font-weight:600"></div>
                                        <button id="dayNext" class="btn" type="button" title="Jour suivant" style="padding:2px 8px">▶</button>
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
        (function () {
            // Horloge (base = heure serveur)
            const base = new Date("{{ $serverNow }}");
            let now = new Date(base.getTime());
            const pad = n => (n < 10 ? '0' : '') + n;
            const draw = () => {
                const d = document.getElementById('srvDateText'), t = document.getElementById('srvTimeText');
                if (d) d.textContent = `${pad(now.getDate())}/${pad(now.getMonth() + 1)}/${now.getFullYear()}`;
                if (t) t.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
            };
            draw();
            setInterval(() => {
                now = new Date(now.getTime() + 60 * 1000);
                draw();
            }, 60 * 1000);

            // Réaffectation : toggle TECH / SAL + agenda
            // ✅ Réaffectation : seulement l’affichage TECH/SAL (plus de agendaWrap/agendaTech ici)
            const reaRadios = document.querySelectorAll('input[name="reaType"]');
            const rowTech = document.getElementById('rowTech');
            const rowSal = document.getElementById('rowSal');
            const selTech = document.getElementById('selTech');

            function setMode(mode) {
                if (!rowTech || !rowSal) return; // garde-fou
                const techMode = mode === 'TECH';
                rowTech.style.display = techMode ? '' : 'none';
                rowSal.style.display = techMode ? 'none' : '';
                if (!techMode && selTech) {
                    selTech.value = '';
                }
            }

            reaRadios.forEach(r => r.addEventListener('change', e => setMode(e.target.value)));
            setMode('TECH');
        })();


        (function () {
            const sel = document.getElementById('selModeTech');
            const calGrid = document.getElementById('calGrid');
            const calTitle = document.getElementById('calTitle');
            const calPrev = document.getElementById('calPrev');
            const calNext = document.getElementById('calNext');
            const calList = document.getElementById('calList');
            const calListTitle = document.getElementById('calListTitle');
            const calListRows = document.getElementById('calListRows');
            const calWrap   = document.getElementById('calWrap');
            const calToggle = document.getElementById('calToggle');
            let   lastShownKey = null;
            const dayNext = document.getElementById('dayNext');
            let   BYDAY = {};   // cache des données par jour (clé 'YYYY-MM-DD' -> {count, items[]})

            if (!sel || !calGrid) return;

            // Tech codes (for fallback ALL aggregation)
            const TECHS = @json($techniciens->pluck('CodeSal')->values());
            const NAMES = Object.fromEntries(@json($techniciens->map(fn($t)=>[$t->CodeSal,$t->NomSal])->values()));

            const pad = n => (n < 10 ? '0' : '') + n;
            const ymd = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
            const frMonth = (y, m) => new Date(y, m, 1).toLocaleDateString('fr-FR', {month: 'long', year: 'numeric'});

            // Visible month state
            let view = new Date();
            view.setDate(1);

            // Heat color from low (green) -> high (red)
            const heat = (val, max) => {
                if (!max) return '#ffffff';
                const t = Math.max(0, Math.min(1, val / max));           // 0..1
                const hue = Math.round(120 * (1 - t));                    // 120=green -> 0=red
                const sat = 80, light = 92 - Math.round(35 * t);          // lighter for low counts
                return `hsl(${hue} ${sat}% ${light}%)`;
            };

            async function fetchRange(code, from, to) {
                const urlBase = `{{ route('api.planning.tech', ['codeTech'=>'__X__']) }}`.replace('__X__', encodeURIComponent(code));
                const url = `${urlBase}?from=${from}&to=${to}&id={{ session('id') }}`;
                const res = await fetch(url, {headers: {'Accept': 'application/json'}});
                const txt = await res.text();
                let data = null;
                try {
                    data = JSON.parse(txt);
                } catch (e) {
                }
                return {ok: !!(data && data.ok === true), data, status: res.status, body: txt};
            }

            async function fetchRangeAll(from, to) {
                // Try server-side _ALL first
                const tryAll = await fetchRange('_ALL', from, to);
                if (tryAll.ok) return tryAll.data.events || [];

                // Fallback: aggregate in client
                const all = [];
                await Promise.all(TECHS.map(async code => {
                    const r = await fetchRange(code, from, to);
                    if (r.ok && r.data && Array.isArray(r.data.events)) {
                        // enforce code_tech present
                        r.data.events.forEach(e => {
                            if (!e.code_tech) e.code_tech = code;
                            all.push(e);
                        });
                    } else {
                        console.warn('Planning fallback fetch failed for', code, r.status, r.body);
                    }
                }));
                return all;
            }

            function monthBounds(d) {
                const y = d.getFullYear(), m = d.getMonth();
                const first = new Date(y, m, 1);
                const last = new Date(y, m + 1, 0); // last day of month
                return {first, last};
            }

            function startOfWeek(d) {
                const r = new Date(d);
                const wd = (r.getDay() + 6) % 7; // Mon=0
                r.setDate(r.getDate() - wd);
                return r;
            }

            function addDays(d, n) {
                const r = new Date(d);
                r.setDate(r.getDate() + n);
                return r;
            }

            function hoursOnly(iso) {
                const dt = new Date(iso);
                return pad(dt.getHours()) + ':' + pad(dt.getMinutes());
            }

            async function render() {
                const {first, last} = monthBounds(view);
                const from = ymd(first), to = ymd(last);
                calTitle.textContent = frMonth(view.getFullYear(), view.getMonth());

                const mode = sel.value || '_ALL';
                let events = [];
                if (mode === '_ALL') {
                    events = await fetchRangeAll(from, to);
                } else {
                    const r = await fetchRange(mode, from, to);
                    if (r.ok) events = r.data.events || []; else events = [];
                }

                // Aggregate count by day
                const byDay = {}; // 'YYYY-MM-DD' => {count, items[]}
                (events || []).forEach(e => {
                    const dkey = (e.start_datetime || '').slice(0, 10);
                    if (!dkey) return;
                    if (!byDay[dkey]) byDay[dkey] = {count: 0, items: []};
                    byDay[dkey].count++;
                    byDay[dkey].items.push(e);
                });
                const maxCount = Object.values(byDay).reduce((m, v) => Math.max(m, v.count), 0);

                // Build grid: 7 weekdays header + 6 rows
                const labels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
                let html = labels.map(w => `<div class="cal-weekday">${w}</div>`).join('');

                const gridStart = startOfWeek(new Date(first));     // Monday before (or equal) 1st
                const totalCells = 42;                               // 6 weeks
                for (let i = 0; i < totalCells; i++) {
                    const day = addDays(gridStart, i);
                    const inMonth = day.getMonth() === view.getMonth();
                    const key = ymd(day);
                    const meta = byDay[key] || {count: 0, items: []};
                    const bg = heat(meta.count, maxCount);
                    html += `<div class="cal-cell ${inMonth ? '' : 'muted'}" data-date="${key}" style="background:${bg}">
        <span class="d">${day.getDate()}</span>
        <span class="dot" title="${meta.count} RDV" style="background:${meta.count ? '#1112' : ''}"></span>
      </div>`;
                }
                calGrid.innerHTML = html;
                BYDAY = byDay;

// Click day => show list (via showDay)
                calGrid.querySelectorAll('.cal-cell').forEach(cell=>{
                    cell.addEventListener('click', ()=>{
                        const key = cell.getAttribute('data-date');
                        showDay(key, byDay);
                    });
                });

// Persistance de la liste : si un jour est déjà choisi, on le ré-affiche
                if (lastShownKey && byDay[lastShownKey]) {
                    showDay(lastShownKey, byDay);
                } else if (calWrap?.classList.contains('collapsed')) {
                    // en mode replié sans sélection -> afficher aujourd'hui (ou 1er jour dispo)
                    const todayKey = ymd(new Date());
                    const fallbackKey = byDay[todayKey] ? todayKey : Object.keys(byDay).sort()[0];
                    if (fallbackKey) showDay(fallbackKey, byDay);
                } else {
                    // mois déplié et aucune sélection -> on peut masquer la liste
                    calList.style.display = 'none';
                }


            }
            function showDay(key, byDay){
                if (!key) return;
                const list = (byDay[key]?.items || []).slice()
                    .sort((a,b)=> (a.start_datetime||'').localeCompare(b.start_datetime||''));
                calListTitle.textContent = `RDV du ${key.split('-').reverse().join('/')}`;
                const rows = list.map(e=>{
                    const hhmm = hoursOnly(e.start_datetime);
                    const tech = e.code_tech || '';
                    const contact = e.contact || '—';
                    const comment = e.label || '';
                    return `<tr>
      <td>${hhmm}</td><td>${tech}</td><td>${contact}</td><td>${comment}</td>
    </tr>`;
                }).join('') || `<tr><td colspan="4" class="note">Aucun rendez-vous</td></tr>`;
                calListRows.innerHTML = rows;
                calList.style.display = '';
                lastShownKey = key;
            }

            // Prev/Next month
            calPrev?.addEventListener('click', () => {
                view.setMonth(view.getMonth() - 1);
                render();
            });
            calNext?.addEventListener('click', () => {
                view.setMonth(view.getMonth() + 1);
                render();
            });

            // Change selection (ALL by default)
            sel.addEventListener('change', () => render());

            function keyToDate(key){ const [y,m,d] = key.split('-').map(Number); return new Date(y, m-1, d); }

            async function goNextDay(){
                // point de départ = jour déjà affiché, sinon aujourd’hui
                const base = lastShownKey ? keyToDate(lastShownKey) : new Date();
                const next = new Date(base.getFullYear(), base.getMonth(), base.getDate()+1);
                const nextKey = ymd(next);

                // si le mois change, on avance la vue puis on re-render avant d'afficher
                const monthChanged = (next.getMonth() !== view.getMonth()) || (next.getFullYear() !== view.getFullYear());
                if (monthChanged){
                    view = new Date(next.getFullYear(), next.getMonth(), 1);
                    await render();             // remet à jour BYDAY
                }
                showDay(nextKey, BYDAY);        // affichera "Aucun rendez-vous" si pas d’items
            }

            dayNext?.addEventListener('click', ()=>{ goNextDay(); });


            function setCollapsed(on){
                if (!calWrap) return;
                calWrap.classList.toggle('collapsed', !!on);
                if (calToggle){
                    calToggle.textContent = on ? '▸ Mois' : '▾ Mois';
                    calToggle.setAttribute('aria-expanded', (!on).toString());
                }
                // Si on replie sans sélection en cours, afficher aujourd'hui
                if (on && !lastShownKey) {
                    const { first } = monthBounds(view);
                    const todayKey = ymd(new Date());
                    // On re-déclenchera showDay après le prochain render()
                }
            }

            calToggle?.addEventListener('click', ()=>{
                const on = !calWrap.classList.contains('collapsed');
                setCollapsed(on);
                // re-render pour recalculer byDay + fallback showDay si besoin
                render();
            });

// Par défaut : calendrier déplié
            setCollapsed(false);


            // Initial
            (function(){
                const box = document.getElementById('agendaBox');

                function sizeAgendaBox(){
                    if(!box) return;
                    const rect = box.getBoundingClientRect();
                    const gap = 12; // marge basse, même esprit que tes paddings
                    const max = window.innerHeight - rect.top - gap;
                    box.style.maxHeight = Math.max(200, max) + 'px';
                }

                // évite que la page scrolle quand on est dans l'agenda
                box?.addEventListener('wheel', (e)=>{
                    const el = box;
                    const delta = e.deltaY;
                    const atTop = el.scrollTop <= 0;
                    const atBottom = Math.ceil(el.scrollTop + el.clientHeight) >= el.scrollHeight;
                    if ((delta < 0 && !atTop) || (delta > 0 && !atBottom)){
                        e.preventDefault();          // on consomme le scroll ici
                        el.scrollTop += delta;
                    }
                }, { passive:false });

                window.addEventListener('resize', sizeAgendaBox);
                // petit délai pour laisser le layout se poser (taille sticky dépend du contenu)
                window.addEventListener('load', ()=>setTimeout(sizeAgendaBox, 0));
                sizeAgendaBox();
            })();
            render();
        })();
    </script>


    {{-- expose l'id session pour la note interne --}}
    <script>window.APP_SESSION_ID = "{{ session('id') }}";</script>
    <script src="{{ asset('js/intervention_note.js') }}" defer></script>
@endsection
