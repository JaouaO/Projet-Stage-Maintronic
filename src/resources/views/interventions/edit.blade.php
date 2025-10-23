@extends('layouts.base')
@section('title', 'Intervention')
<?php $exp_ui = true; ?>

@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/intervention_edit.css') }}">
    <form id="interventionForm"
          method="POST"
          onsubmit="return confirm('Confirmer la validation?');"
          action="{{ route('interventions.update', $interv->NumInt) }}">
        @csrf

        <input type="hidden" name="code_sal_auteur" value="{{ $data->CodeSal ?? 'Utilisateur' }}">
        <input type="hidden" name="marque" value="{{$interv->Marque ?? ''}}">
        <input type="hidden" name="objet_trait" value="{{$objetTrait ?? ''}}">
        <input type="hidden" name="code_postal" value="{{$interv->CPLivCli ?? ''}}">
        <input type="hidden" name="ville" value="{{$interv->VilleLivCli ?? ''}}">
        <input type="hidden" name="action_type" id="actionType" value="">


        @if ($errors->any())
            <div id="formErrors" class="alert alert--error box">
                <div class="body">
                    <strong class="alert-title">Le formulaire contient des erreurs :</strong>
                    <ul class="alert-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif



        <div class="app">
            {{-- Gauche : Traitement du dossier --}}
            <section class="col center">
                <div class="box">
                    <div class="head">
                        <strong>Traitement du dossier — {{ $interv->NumInt }}</strong>
                        <span class="note">{{ $data->NomSal ?? ($data->CodeSal ?? '—') }}</span>
                    </div>
                    <div class="body">
                        {{-- Objet sur toute la ligne (fusion avec grid2) --}}
                        <div class="grid2">
                            <label>Objet</label>
                            <div class="ro">{{ $objetTrait ?: '—' }}</div>
                        </div>

                        {{-- Contact réel SEUL (ligne dédiée) --}}
                        <div class="grid2">
                            <label for="contactReel">Contact réel</label>
                            <input type="text" id="contactReel" name="contact_reel"
                                   maxlength="255"
                                   value="{{ old('contact_reel', $contactReel) }}"
                                   class="{{ $errors->has('contact_reel') ? 'is-invalid' : '' }}"
                                   aria-invalid="{{ $errors->has('contact_reel') ? 'true' : 'false' }}"
                            >
                        </div>

                        {{-- Bouton historique (plein largeur, un peu plus visible) --}}
                        <button id="openHistory"
                                class="btn btn-history btn-block"
                                type="button"
                                data-num-int="{{ $interv->NumInt }}">
                            Ouvrir l’historique
                        </button>

                        {{-- Checklist TRAITEMENT --}}
                        <div class="table mt6 {{ $errors->has('traitement') || $errors->has('traitement.*') ? 'is-invalid-block' : '' }}">                            <table>
                                <tbody>
                                @php $traits = $traitementItems ?? []; @endphp
                                @forelse($traits as $trait)
                                    <tr>
                                        <td>{{ $trait['label'] }}</td>
                                        <td class="status">
                                            <input type="hidden" name="traitement[{{ $trait['code'] }}]" value="0">
                                            <input type="checkbox"
                                                   name="traitement[{{ $trait['code'] }}]"
                                                   value="1"
                                                {{ old("traitement.{$trait['code']}") === '1' ? 'checked' : '' }}>
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

                {{-- Gabarit HTML injecté dans la nouvelle fenêtre (sans CSS inline) --}}
                <template id="tplHistory">
                    <div class="hist-wrap">
                        <h2 class="hist-title">Historique du dossier {{ $interv->NumInt }}</h2>
                        <table class="hist-table table">
                            <thead>
                            <tr>
                                <th class="w-150">Date</th>
                                <th>Résumé</th>
                                <th class="w-200">Rendez-vous / Appel</th>
                                <th class="w-40"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($suivis as $suivi)
                                @php
                                    $dateTxt = '—';

                                    $raw = (string)($suivi->Texte ?? '');
                                    $resumeLine = trim(preg_split('/\R/', $raw, 2)[0] ?? '');
                                    $resumeClean = rtrim($resumeLine, " \t—–-:;.,");
                                    $resumeShort = \Illuminate\Support\Str::limit($resumeClean !== '' ? $resumeClean : '—', 60, '…');
                                    $objet = trim((string)($suivi->Titre ?? ''));
                                    $auteur = trim((string)($suivi->CodeSalAuteur ?? ''));

                                    $evtType = $suivi->evt_type ?? null;
                                    $meta = [];
                                    if (!empty($suivi->evt_meta)) {
                                        try { $meta = (is_array($suivi->evt_meta) ? $suivi->evt_meta : json_decode($suivi->evt_meta, true)) ?: []; }
                                        catch (\Throwable $e) { $meta = []; }
                                    }

                                    $dateIso = $meta['date'] ?? $meta['d'] ?? null; // legacy/compact
                                    $heure   = $meta['heure'] ?? $meta['h'] ?? null;
                                    $tech    = $meta['tech']  ?? $meta['t'] ?? null;
                                    $urgent  = (int) (
                                        (isset($meta['urg']) && $meta['urg'])      // compact
                                        || (isset($meta['urgent']) && $meta['urgent']) // legacy éventuel
                                    );

                                    // (facultatif) listes par ligne si déjà présentes dans evt_meta compacte
                                    $traitementList  = $meta['tl'] ?? [];
                                    $affectationList = $meta['al'] ?? [];

                                    // Date d'affichage
                                    $dateTxt = '—';
                                    if ($dateIso && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateIso)) {
                                        [$yy,$mm,$dd] = explode('-', $dateIso);
                                        $dateTxt = "$dd/$mm/$yy";
                                    }

                                    // Label d'événement existant...
                                    $evtLabel = null; $evtClass = null;
                                    switch ($evtType) {
                                        case 'CALL_PLANNED':      $evtLabel='Appel planifié';              $evtClass='badge-call';  break;
                                        case 'RDV_TEMP_INSERTED': $evtLabel='RDV temporaire (créé)';       $evtClass='badge-temp';  break;
                                        case 'RDV_TEMP_UPDATED':  $evtLabel='RDV temporaire (mis à jour)'; $evtClass='badge-temp';  break;
                                        case 'RDV_FIXED':         $evtLabel='RDV validé';                  $evtClass='badge-valid'; break;
                                    }
                                    if ($evtLabel) {
                                        $parts = [];
                                        if ($dateTxt && $heure)      $parts[] = $dateTxt.' à '.$heure;
                                        elseif ($dateTxt)             $parts[] = $dateTxt;
                                        elseif ($heure)               $parts[] = $heure;
                                        if ($tech) $parts[] = $tech;
                                        if (!empty($parts)) $evtLabel .= ' — ' . implode(' · ', $parts);
                                    }
                                @endphp

                                <tr class="row-main" data-row="main">
                                    <td class="cell-p6">{{ $dateTxt }}</td>
                                    <td class="cell-p6" title="{{ $resumeClean }}">
                                        @if($auteur !== '')
                                            <strong>{{ $auteur }}</strong> —
                                        @endif
                                        @if($objet  !== '')
                                            <em>{{ $objet }}</em> —
                                        @endif
                                        {{ $resumeShort }}
                                    </td>

                                    <td class="cell-p6">
                                        @if($evtLabel)
                                            <span class="badge {{ $evtClass }}">{{ $evtLabel }}</span>
                                            @if($urgent)
                                                <span class="badge badge-urgent" aria-label="Dossier urgent">URGENT</span>
                                            @endif
                                        @else
                                            <span class="note">—</span>
                                        @endif
                                    </td>

                                    <td class="cell-p6 cell-center">
                                        <button class="hist-toggle" type="button" aria-expanded="false"
                                                title="Afficher le détail">+
                                        </button>
                                    </td>
                                </tr>

                                <tr class="row-details" data-row="details">
                                    <td colspan="3" class="hist-details-cell">
                                        @if($suivi->CodeSalAuteur)
                                            <div><strong>Auteur :</strong> {{ $suivi->CodeSalAuteur }}</div>
                                        @endif
                                        @if($suivi->Titre)
                                            <div><strong>Titre :</strong> <em>{{ $suivi->Titre }}</em></div>
                                        @endif

                                        {{-- Affichage complet du commentaire sans filtrage --}}
                                        <div class="prewrap mt8">{{ (string)($suivi->Texte ?? '') }}</div>

                                        @if(!empty($traitementList) || !empty($affectationList))
                                            <hr class="sep">
                                            <div class="grid-2">
                                                <div>
                                                    <div class="section-title">Tâches effectuées</div>
                                                    @if(!empty($traitementList))
                                                        <div class="chips-wrap">
                                                            @foreach($traitementList as $lbl)
                                                                <span class="chip chip-green">{{ $lbl }}</span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <div class="note">—</div>
                                                    @endif
                                                </div>
                                                <div>
                                                    <div class="section-title">Affectation</div>
                                                    @if(!empty($affectationList))
                                                        <div class="chips-wrap">
                                                            @foreach($affectationList as $lbl)
                                                                <span class="chip chip-amber">{{ $lbl }}</span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <div class="note">—</div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="note cell-p8-10">Aucun suivi</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </template>

                <div class="box mserv">
                    <div class="head"><label for="commentaire"><strong>Commentaire</strong></label><span class="note">infos utiles</span>
                    </div>
                    <div class="body">
                        <div>
                            <input
                                type="text"
                                id="commentaire"
                                name="commentaire"
                                maxlength="249"
                                value="{{ old('commentaire') }}"
                                class="{{ $errors->has('commentaire') ? 'is-invalid' : '' }}"
                                aria-invalid="{{ $errors->has('commentaire') ? 'true' : 'false' }}"
                            >
                        </div>
                    </div>
                </div>
            </section>

            {{-- DROITE : Affectation + Agenda du technicien --}}
            <section class="col right">
                <div class="box">
                    <div class="head">
                        <strong>Affectation du dossier</strong>
                        <span id="srvDateTimeText" class="note">—</span>
                    </div>
                    <div class="body">

                        <div class="affectationSticky">
                            {{-- Affecter à (liste unique) --}}
                            <div class="grid2">
                                <label for="selAny">Affecter à</label>
                                <div class="hstack-12">
                                    <select
                                        name="rea_sal"
                                        id="selAny"
                                        required
                                        class="{{ $errors->has('rea_sal') ? 'is-invalid' : '' }}"
                                        aria-invalid="{{ $errors->has('rea_sal') ? 'true' : 'false' }}"
                                    >
                                        <option value="">— Sélectionner —</option>
                                        @if(($techniciens ?? collect())->count())
                                            <optgroup label="Techniciens">
                                                @foreach($techniciens as $t)
                                                    <option value="{{ $t->CodeSal }}"
                                                        {{ old('rea_sal') == $t->CodeSal ? 'selected' : '' }}>
                                                        {{ $t->NomSal }} ({{ $t->CodeSal }})
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        @if(($salaries ?? collect())->count())
                                            <optgroup label="Salariés">
                                                @foreach($salaries as $s)
                                                    <option value="{{ $s->CodeSal }}"
                                                        {{ old('rea_sal') == $s->CodeSal ? 'selected' : '' }}>
                                                        {{ $s->NomSal }} ({{ $s->CodeSal }})
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </select>

                                    {{-- URGENT (hidden 0 + checkbox 1) --}}
                                    <label class="urgent-toggle" for="urgent">
                                        <input type="hidden" name="urgent" value="0">
                                        <input type="checkbox" id="urgent" name="urgent" value="1"
                                            {{ old('urgent') == '1' ? 'checked' : '' }}>
                                        <span>Urgent</span>
                                    </label>
                                </div>
                            </div>


                            <div class="gridRow gridRow--dt">
                                <label for="dtPrev">Date</label>
                                <input
                                    type="date"
                                    id="dtPrev"
                                    name="date_rdv"
                                    required
                                    value="{{ old('date_rdv') }}"
                                    class="{{ $errors->has('date_rdv') ? 'is-invalid' : '' }}"
                                    aria-invalid="{{ $errors->has('date_rdv') ? 'true' : 'false' }}"
                                >
                                <label for="tmPrev">Heure</label>
                                <input
                                    type="time"
                                    id="tmPrev"
                                    name="heure_rdv"
                                    required
                                    value="{{ old('heure_rdv') }}"
                                    class="{{ $errors->has('heure_rdv') ? 'is-invalid' : '' }}"
                                    aria-invalid="{{ $errors->has('heure_rdv') ? 'true' : 'false' }}"
                                >
                            </div>

                            {{-- Étapes AFFECTATION en 2 colonnes --}}
                            <div class="table mt8 {{ $errors->has('affectation') || $errors->has('affectation.*') ? 'is-invalid-block' : '' }}">                                <table>
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
                                            <td class="status">
                                                @if(isset($pair[0]))
                                                    <input type="hidden" name="affectation[{{ $pair[0]['code'] }}]"
                                                           value="0">
                                                    <input type="checkbox" name="affectation[{{ $pair[0]['code'] }}]"
                                                           value="1">
                                                @endif
                                            </td>
                                            <td>{{ $pair[1]['label'] ?? '' }}</td>
                                            <td class="status">
                                                @if(isset($pair[1]))
                                                    <input type="hidden" name="affectation[{{ $pair[1]['code'] }}]"
                                                           value="0">
                                                    <input type="checkbox" name="affectation[{{ $pair[1]['code'] }}]"
                                                           value="1">
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="note">Aucun item d’affectation</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            {{-- Boutons sous les étapes --}}
                            <div class="flex-end-bar">
                                <button id="btnPlanifierAppel" class="btn btn-plan-call btn-sm" type="button">
                                    Planifier un nouvel appel
                                </button>

                                <button id="btnPlanifierRdv" class="btn btn-plan-rdv btn-sm" type="button">
                                    Planifier un rendez-vous
                                </button>

                                <button id="btnValider" class="btn btn-validate" type="button">
                                    Valider le prochain rendez-vous
                                </button>
                            </div>
                        </div>

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
                                        @foreach($techniciens as $technicien)
                                            <option value="{{ $technicien->CodeSal }}">{{ $technicien->NomSal }}
                                                ({{ $technicien->CodeSal }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Calendrier + toggle --}}
                                <div id="calWrap">
                                    <div id="calHead">
                                        <button id="calPrev" class="btn" type="button">◀</button>

                                        <div id="calHeadMid">
                                            <div id="calTitle"></div>
                                            <button id="calToggle" class="btn" type="button" aria-expanded="true">▾
                                                Mois
                                            </button>
                                        </div>

                                        <button id="calNext" class="btn" type="button">▶</button>
                                    </div>

                                    {{-- Grille du mois (heat-map) --}}
                                    <div id="calGrid" class="cal-grid"></div>

                                    {{-- Liste du jour sélectionné --}}
                                    <div id="calList" class="cal-list is-hidden">
                                        <div id="calListHead">
                                            <button id="dayPrev" class="btn" type="button" title="Jour précédent"
                                                    aria-label="Jour précédent">◀
                                            </button>
                                            <div id="calListTitle"></div>
                                            <button id="dayNext" class="btn" type="button" title="Jour suivant"
                                                    aria-label="Jour suivant">▶
                                            </button>
                                        </div>
                                        <div id="calListBody" class="table">
                                            <table>
                                                <thead>
                                                <tr>
                                                    <th class="w-80">Heure</th>
                                                    <th class="w-80">Tech</th>
                                                    <th class="w-200">Contact</th>
                                                    <th>Label</th>
                                                    <th class="col-icon"></th>
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
    </form>

    <div id="infoModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="modal-content" role="document">
            <button type="button" class="close" aria-label="Fermer" id="infoModalClose">×</button>
            <div id="infoModalBody"></div>
        </div>
    </div>

    <script>
        window.APP = {
            serverNow: "{{ $serverNow }}",
            sessionId: "{{ session('id') }}",
            apiPlanningRoute: "{{ route('api.planning.tech', ['codeTech' => '__X__']) }}",
            TECHS: @json($techniciens->pluck('CodeSal')->values()),
            NAMES: @json($techniciens->mapWithKeys(fn($technicien)=>[$technicien->CodeSal=>$technicien->NomSal])),
            techs: @json($techniciens->pluck('CodeSal')->values()),
            names: @json($techniciens->mapWithKeys(fn($technicien)=>[$technicien->CodeSal=>$technicien->NomSal])),
        };
        window.APP_SESSION_ID = "{{ session('id') }}";
    </script>

    @php($v = filemtime(public_path('js/interventions_edit/main.js')))
    <script type="module" src="{{ asset('js/interventions_edit/main.js') }}"></script>
@endsection
