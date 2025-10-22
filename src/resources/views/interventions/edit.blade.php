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
        <input type="hidden" name="code_postal" value="{{$interv-> CPLivCli ?? ''}}">
        <input type="hidden" name="ville" value="{{$interv->VilleLivCli ?? ''}}">
        <input type="hidden" name="rdv_validated_by_ajax" id="rdvValidatedByAjax" value="">
        <input type="hidden" name="action_type" id="actionType" value="">

        <div class="app">
            {{-- Gauche : Traitement du dossier --}}
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
                            <label for="contactReel">Contact réel</label>
                            <input type="text" id="contactReel" name="contact_reel"
                                   maxlength="255"
                                   value="{{ old('contact_reel', $contactReel) }}">
                        </div>

                        {{-- Bouton historique (plein largeur, un peu plus visible) --}}
                        <button id="openHistory"
                                class="btn btn-history btn-block"
                                type="button"
                                data-num-int="{{ $interv->NumInt }}">
                            Ouvrir l’historique
                        </button>


                        {{-- Checklist TRAITEMENT --}}
                        <div class="table mt6">
                            <table>
                                <tbody>
                                @php $traits = $traitementItems ?? []; @endphp
                                @forelse($traits as $trait)
                                    <tr>
                                        <td>{{ $trait['label'] }}</td>
                                        <td class="status">
                                            {{-- valeur par défaut 0 si non coché --}}
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


                {{-- Gabarit HTML injecté dans la nouvelle fenêtre --}}
                <template id="tplHistory">
                    <div class="hist-wrap">
                        <h2 style="margin:6px 0 12px 0;">Historique du dossier {{ $interv->NumInt }}</h2>
                        <table class="hist-table" style="width:100%;border-collapse:collapse">
                            <thead>
                            <tr>
                                <th style="width:150px;text-align:left;border-bottom:1px solid #ddd;">Date</th>
                                <th style="text-align:left;border-bottom:1px solid #ddd;">Résumé</th>
                                <th style="width:200px;text-align:left;border-bottom:1px solid #ddd;">Rendez-vous /
                                    Appel
                                </th>
                                <th style="width:40px;border-bottom:1px solid #ddd;"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($suivis as $suivi)
                                @php
                                    $dateTxt = '—';
                                    if ($suivi->CreatedAt) {
                                        try {
                                            $dt = \Carbon\Carbon::parse($suivi->CreatedAt);
                                            $fmt = $dt->toTimeString() !== '00:00:00' ? 'd/m/Y H:i' : 'd/m/Y';
                                            $dateTxt = $dt->format($fmt);
                                        } catch (\Exception $e) {}
                                    }

                                    // Ligne courte = 1ère ligne du texte
                                    $raw = (string)($suivi->Texte ?? '');
                                    $resumeLine = trim(preg_split('/\R/', $raw, 2)[0] ?? '');
                                    $resumeClean = rtrim($resumeLine, " \t—–-:;.,");

                                    $objet = trim((string)($suivi->Titre ?? ''));
                                    $auteur = trim((string)($suivi->CodeSalAuteur ?? ''));
                                    // meta lisibles
                                    $evtType = $suivi->evt_type ?? null;
                                    $meta = [];
                                    if (!empty($suivi->evt_meta)) {
                                        try { $meta = (is_array($suivi->evt_meta) ? $suivi->evt_meta : json_decode($suivi->evt_meta, true)) ?: []; }
                                        catch (\Throwable $e) { $meta = []; }
                                    }

                                    $dateIso = $meta['date'] ?? null;                 // "YYYY-MM-DD"
                                    $dateTxt = null;
                                    if ($dateIso && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateIso)) {
                                        $parts = explode('-', $dateIso);
                                        $dateTxt = $parts[2].'/'.$parts[1].'/'.$parts[0]; // JJ/MM/AAAA
                                    }

                                    $heure = $meta['heure'] ?? null;
                                    $tech  = $meta['tech']  ?? null;

                                    $evtLabel = null; $evtClass = null;
                                    switch ($evtType) {
                                        case 'CALL_PLANNED':       $evtLabel = 'Appel planifié';                 $evtClass='badge-call';  break;
                                        case 'RDV_TEMP_INSERTED':  $evtLabel = 'RDV temporaire (créé)';          $evtClass='badge-temp';  break;
                                        case 'RDV_TEMP_UPDATED':   $evtLabel = 'RDV temporaire (mis à jour)';    $evtClass='badge-temp';  break;
                                        case 'RDV_FIXED':          $evtLabel = 'RDV validé';                     $evtClass='badge-valid'; break;
                                    }

                                    if ($evtLabel) {
                                        $parts = [];
                                        if ($dateTxt && $heure) {
                                            $parts[] = $dateTxt.' à '.$heure;
                                        } elseif ($dateTxt) {
                                            $parts[] = $dateTxt;
                                        } elseif ($heure) {
                                            $parts[] = $heure;
                                        }
                                        if ($tech) $parts[] = $tech;
                                        if (!empty($parts)) $evtLabel .= ' — ' . implode(' · ', $parts);
                                    }

                                    // Texte brut d'origine (inchangé)
                                    $raw = (string)($suivi->Texte ?? '');
                                                                @endphp


                                <tr class="row-main" data-row="main" style="border-bottom:1px solid #f0f0f0;">
                                    <td style="padding:6px 8px;">{{ $dateTxt }}</td>
                                    <td style="padding:6px 8px;">
                                        @if($auteur !== '')
                                            <strong>{{ $auteur }}</strong> —
                                        @endif
                                        @if($objet  !== '')
                                            <em>{{ $objet }}</em> —
                                        @endif
                                        {{ $resumeClean !== '' ? $resumeClean : '—' }}
                                    </td>

                                    <td style="padding:6px 8px;">
                                        @if($evtLabel)
                                            <span class="badge {{ $evtClass }}">{{ $evtLabel }}</span>
                                        @else
                                            <span class="note">—</span>
                                        @endif
                                    </td>


                                    <td style="padding:6px 8px;text-align:center;">
                                        <button class="hist-toggle" type="button" aria-expanded="false"
                                                title="Afficher le détail">+
                                        </button>
                                    </td>
                                </tr>

                                <tr class="row-details" data-row="details" style="display:none;">
                                    <td colspan="3"
                                        style="padding:10px 12px;background:#fafafa;border-bottom:1px solid #eee;">
                                        {{-- Détail complet du suivi --}}
                                        @if($suivi->CodeSalAuteur)
                                            <div><strong>Auteur :</strong> {{ $suivi->CodeSalAuteur }}</div>
                                        @endif
                                        @if($suivi->Titre)
                                            <div><strong>Titre :</strong> <em>{{ $suivi->Titre }}</em></div>
                                        @endif

                                        {{-- Affichage complet du commentaire sans filtrage --}}
                                        <div style="margin-top:8px;white-space:pre-wrap;">{{ $raw }}</div>

                                        @if(!empty($traitementList) || !empty($affectationList))
                                            <hr style="border:none;border-top:1px solid #e5e7eb; margin:10px 0">
                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                                                <div>
                                                    <div style="font-weight:600;margin-bottom:6px;">Tâches effectuées
                                                    </div>
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
                                                    <div style="font-weight:600;margin-bottom:6px;">Affectation</div>
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
                                    <td colspan="3" class="note" style="padding:8px 10px;">Aucun suivi</td>
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
                            <input type="text" id="commentaire" name="commentaire" maxlength="249">
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
                            {{-- Choix du type --}}
                            {{-- Affecter à (liste unique) --}}
                            <div class="grid2">
                                <label for="selAny">Affecter à</label>
                                <select name="rea_sal" id="selAny" required>
                                    <option value="">— Sélectionner —</option>

                                    @if(($techniciens ?? collect())->count())
                                        <optgroup label="Techniciens">
                                            @foreach($techniciens as $t)
                                                <option value="{{ $t->CodeSal }}">
                                                    {{ $t->NomSal }} ({{ $t->CodeSal }})
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif

                                    @if(($salaries ?? collect())->count())
                                        <optgroup label="Salariés">
                                            @foreach($salaries as $s)
                                                <option value="{{ $s->CodeSal }}">
                                                    {{ $s->NomSal }} ({{ $s->CodeSal }})
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                            </div>

                            <div class="gridRow gridRow--dt">
                                <label for="dtPrev">Date</label>
                                <input type="date" id="dtPrev" name="date_rdv" required>

                                <label for="tmPrev">Heure</label>
                                <input type="time" id="tmPrev" name="heure_rdv" required>
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


                            <!-- /affectationSticky -->
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
                                                    <th class="col-icon"></th> {{-- icône info --}}
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

    @php($v = filemtime(public_path('js/intervention_edit.js')))
    <script src="{{ asset('js/intervention_edit.js') }}?v={{ $v }}" defer></script>

@endsection
