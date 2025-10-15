@extends('layouts.base')
@section('title', 'Intervention')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/intervention_edit.css') }}">

    <div class="app">

        {{-- GAUCHE --}}
        <section class="col left">
            <div class="box hist">
                <div class="head"><strong>Historique (serveur)</strong><span class="note">résumé</span></div>
                <div class="body table">
                    <table>
                        <thead>
                        <tr>
                            <th class="w-150">Date (srv)</th>
                            <th>Action / Commentaire</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($suivis as $s)
                            @php
                                $dateTxt='—';
                                if($s->CreatedAt){
                                    try{
                                        $dt=\Carbon\Carbon::parse($s->CreatedAt);
                                        $dateTxt=$dt->format($dt->toTimeString()!=='00:00:00'?'d/m/Y H:i':'d/m/Y');
                                    }catch(\Exception $e){}
                                }
                            @endphp
                            <tr>
                                <td>{{ $dateTxt }}</td>
                                <td>
                                    @if($s->CodeSalAuteur)<strong>{{ $s->CodeSalAuteur }}</strong> — @endif
                                    @if($s->Titre)<em>{{ $s->Titre }}</em> — @endif
                                    {{ $s->Texte }}
                                </td>
                            </tr>
                        @empty
                            <tr><td>—</td><td>Aucun suivi</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="box mserv">
                <div class="head"><strong>mServ</strong><span class="note">notes internes</span></div>
                <div class="body">
                    <div id="noteInterne"
                         data-update-url="{{ route('interventions.note.update', ['numInt'=>$interv->NumInt]) }}">
                        {{ $noteInterne }}
                    </div>
                    <div class="note-toolbar">
                        <button id="btnEdit" class="btn" type="button">Modifier</button>
                        <button id="btnSave" class="btn is-hidden" type="button">Enregistrer</button>
                        <button id="btnCancel" class="btn is-hidden" type="button">Annuler</button>
                        <span id="noteCounter" class="note ml-auto"></span>
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

                    {{-- Checklist TRAITEMENT --}}
                    <div class="table mt6">
                        <table>
                            <thead>
                            <tr>
                                <th>Action de traitement</th>
                                <th class="w-66">Statut</th>
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
                                <tr><td colspan="2" class="note">Aucun item de traitement</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </section>

        {{-- DROITE : Affectation + Agenda du technicien --}}
        <section class="col right">
            <div class="box">
                <div class="head"><strong>Affectation du dossier</strong></div>
                <div class="body">

                    <div class="affectationSticky">
                        {{-- Choix du type --}}
                        <div class="grid2">
                            <label>Réaffecter à</label>
                            <div class="hstack-8">
                                <label class="hstack-6">
                                    <input type="radio" name="reaType" value="TECH" checked>
                                    Technicien
                                </label>
                                <label class="hstack-6">
                                    <input type="radio" name="reaType" value="SAL">
                                    Salarié
                                </label>
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

                        <div class="grid2 is-hidden" id="rowSal">
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
                        <div class="table mt8">
                            <table>
                                <thead>
                                <tr>
                                    <th>Étapes de planification</th>
                                    <th class="w-66">Statut</th>
                                    <th>Étapes de planification</th>
                                    <th class="w-66">Statut</th>
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
                                    <tr><td colspan="4" class="note">Aucun item d’affectation</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Bouton sous les étapes --}}
                        <div class="flex-end-bar">
                            <button id="btnPlanifier" class="btn ok" type="button">
                                Valider le prochain rendez-vous
                            </button>
                        </div>
                    </div>
                    <!-- /affectationSticky -->

                    {{-- Agenda du technicien --}}
                    <div class="box agendaBox" id="agendaBox">
                        <div class="head">
                            <strong>Agenda technicien</strong>
                            <span class="note">vue mensuelle (Tous par défaut)</span>
                        </div>
                        <div class="body">

                            {{-- Sélecteur de technicien --}}
                            <div class="grid2">
                                <label>Technicien</label>
                                <select id="selModeTech">
                                    <option value="_ALL" selected>Tous les techniciens</option>
                                    @foreach($techniciens as $t)
                                        <option value="{{ $t->CodeSal }}">{{ $t->NomSal }} ({{ $t->CodeSal }})</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Calendrier + toggle --}}
                            <div id="calWrap">
                                <div id="calHead">
                                    <button id="calPrev" class="btn" type="button">◀</button>

                                    <div id="calHeadMid">
                                        <div id="calTitle"></div>
                                        <button id="calToggle" class="btn" type="button" aria-expanded="true">▾ Mois</button>
                                    </div>

                                    <button id="calNext" class="btn" type="button">▶</button>
                                </div>

                                {{-- Grille du mois (heat-map) --}}
                                <div id="calGrid" class="cal-grid"></div>

                                {{-- Liste du jour sélectionné --}}
                                <div id="calList" class="cal-list is-hidden">
                                    <div id="calListHead">
                                        <div id="calListTitle"></div>
                                        <button id="dayNext" class="btn" type="button" title="Jour suivant">▶</button>
                                    </div>
                                    <div id="calListBody" class="table">
                                        <table>
                                            <thead>
                                            <tr>
                                                <th class="w-80">Heure</th>
                                                <th class="w-80">Tech</th>
                                                <th class="w-200">Contact</th>
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
            TECHS: @json($techniciens->pluck('CodeSal')->values()),
            NAMES: @json($techniciens->mapWithKeys(fn($t)=>[$t->CodeSal=>$t->NomSal])),
            techs: @json($techniciens->pluck('CodeSal')->values()),
            names: @json($techniciens->mapWithKeys(fn($t)=>[$t->CodeSal=>$t->NomSal])),
        };
        window.APP_SESSION_ID = "{{ session('id') }}";
    </script>
    <script src="{{ asset('js/intervention_edit.js') }}" defer></script>
@endsection
