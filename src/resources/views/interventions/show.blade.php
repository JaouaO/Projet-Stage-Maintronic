@extends('layouts.base')
@section('title','Tickets / récap')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/interventions.css') }}">

    <div class="app">
        <div class="header">
            <h1>File des tickets</h1>
            <div class="toolbar">
                <form method="get" action="{{ route('interventions.show') }}">
                    <label for="perpage">Lignes / page :</label>
                    <select id="perpage" name="per_page" onchange="this.form.submit()">
                        @foreach([10,25,50,100] as $pp)
                            <option value="{{ $pp }}" {{ (int)$perPage===$pp ? 'selected':'' }}>{{ $pp }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit">OK</button></noscript>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="cardHead">
                <strong>Tickets en attente</strong>
                <div class="right" style="display:flex;gap:8px;align-items:center;color:var(--mut);font-size:12px">
                    <span class="badge badge-urgent">URGENT</span>
                    <span class="badge badge-me">VOUS</span>
                </div>
            </div>

            <div class="cardBody">
                <div class="tableArea">
                    <table class="table" id="intervTable">
                        <colgroup>
                            <col style="width:150px">  {{-- N° Interv --}}
                            <col style="width:280px">  {{-- Client --}}
                            <col style="width:110px">  {{-- Date --}}
                            <col style="width:80px">   {{-- Heure --}}
                            <col style="width:360px">  {{-- À faire --}}
                            <col style="width:170px">  {{-- Badges URGENT/VOUS --}}
                            <col style="width:180px">  {{-- Actions (Ouvrir + Histo + Chevron) --}}
                        </colgroup>

                        <thead>
                        <tr>
                            <th class="col-id" data-sort="text">N° Interv</th>
                            <th class="col-client" data-sort="text">Client</th>
                            <th class="col-date" data-sort="date">Date</th>
                            <th class="col-heure" data-sort="time">Heure</th>
                            <th class="col-todo" data-sort="text">À faire</th>
                            <th class="col-flags"></th>
                            <th class="col-actions">Actions</th>
                        </tr>
                        </thead>

                        <tbody id="rowsBody">
                        @forelse($rows as $row)
                            @php
                                $isUrgent = (int)($row->urgent ?? 0) === 1;
                                $isMe     = (int)($row->concerne ?? 0) === 1;

                                // AFFECTATIONS (array [{code,label}] ou fallback texte)
                                $aff = [];
                                if (!empty($row->affectations) && (is_array($row->affectations) || $row->affectations instanceof \Traversable)) {
                                  foreach ($row->affectations as $a) {
                                    $code  = is_array($a) ? ($a['code'] ?? null) : ($a->code ?? null);
                                    $label = is_array($a) ? ($a['label'] ?? null) : ($a->label ?? null);
                                    if ($label) $aff[] = ['code'=>$code, 'label'=>$label];
                                  }
                                } else {
                                  $raw = trim((string)($row->a_faire_label ?? $row->a_faire ?? ''));
                                  if ($raw !== '') {
                                    $parts = preg_split('/\s*[|,;\/·]\s*/u', $raw);
                                    foreach ($parts as $p) { $p = trim($p); if ($p !== '') $aff[] = ['code'=>null, 'label'=>$p]; }
                                  }
                                }

                                // Mapping label → code minimal
                                $mapLabelToCode = [
                                  'RDV à fixer' => 'RDV_A_FIXER',
                                  'Client à recontacter' => 'CLIENT_A_RECONTACTER',
                                  'Commande pièces' => 'COMMANDE_PIECES',
                                  'Suivi escalade' => 'SUIVI_ESCALADE',
                                  'Confirmer contact client' => 'CONFIRMER_CONTACT',
                                  'Diagnostic à réaliser' => 'DIAGNOSTIC_A_REALISER',
                                  'Vérifier dispo pièce' => 'VERIFIER_DISPO_PIECE',
                                  'RDV confirmé' => 'RDV_CONFIRME',
                                ];
                                foreach ($aff as &$a) { if (empty($a['code'])) $a['code'] = $mapLabelToCode[$a['label']] ?? null; }
                                unset($a);

                                $affCount = count($aff);
                                $affFull  = $affCount >= 3 ? implode(' · ', array_map(fn($x)=>$x['label'],$aff)) : null;

                                // classes de ligne (liseré) + teinte douce
                                $trClassBase = $isUrgent && $isMe ? 'row-urgent-me' : ($isUrgent ? 'row-urgent' : ($isMe ? 'row-me' : ''));
                                $trClassTint = $isUrgent ? 'tint-urgent' : ($isMe ? 'tint-me' : '');
                                $trClass     = trim($trClassBase.' '.$trClassTint);

                                $rowId   = 'r_'.preg_replace('/[^A-Za-z0-9_-]/','',$row->num_int);
                            @endphp

                            {{-- LIGNE PRINCIPALE --}}
                            <tr class="row {{ $trClass }}" data-href="{{ route('interventions.edit', ['numInt' => $row->num_int]) }}" data-row-id="{{ $rowId }}">
                                <td class="col-id">{{ $row->num_int }}</td>
                                <td class="col-client">{{ $row->client }}</td>
                                <td class="col-date">{{ $row->date_prev ? \Carbon\Carbon::parse($row->date_prev)->format('d/m/Y') : '—' }}</td>
                                <td class="col-heure">{{ $row->heure_prev ? \Carbon\Carbon::parse($row->heure_prev)->format('H:i') : '—' }}</td>

                                {{-- À FAIRE --}}
                                <td class="col-todo">
                                    @if($affCount >= 3)
                                        <span class="tag combo" aria-label="{{ $affFull }}" title="{{ $affFull }}">{{ $affFull }}</span>
                                    @else
                                        <span class="todo-tags">
                                            @foreach($aff as $a)
                                                @php $cls = $a['code'] ? 't-'.$a['code'] : 't-OTHER'; @endphp
                                                <span class="tag {{ $cls }}">{{ $a['label'] }}</span>
                                            @endforeach
                                        </span>
                                    @endif
                                </td>

                                {{-- BADGES URGENT / VOUS (VOUS reste bleu) --}}
                                <td class="col-flags">
                                    <span class="flags">
                                        @if($isUrgent)<span class="badge badge-urgent">URGENT</span>@endif
                                        @if($isMe)<span class="badge badge-me">VOUS</span>@endif
                                    </span>
                                </td>

                                {{-- ACTIONS : Ouvrir, Historique (lazy), Chevron accordéon --}}
                                <td class="col-actions">
                                    <div class="actions">
                                        <a class="btn js-open" href="{{ route('interventions.edit', ['numInt' => $row->num_int]) }}">Ouvrir</a>
                                        <button
                                            class="btn btn-light js-open-history"
                                            type="button"
                                            data-num-int="{{ $row->num_int }}"
                                            data-history-url="{{ route('interventions.history', $row->num_int) }}"
                                            title="Historique">Historique</button>
                                        <button class="icon-toggle js-row-toggle" type="button"
                                                aria-expanded="false" aria-controls="det-{{ $rowId }}"
                                                title="Plus d’infos" data-row-id="{{ $rowId }}">▾</button>
                                    </div>
                                </td>
                            </tr>

                            {{-- LIGNE DÉTAIL (ACCORDÉON) : marque, ville, cp, commentaire --}}
                            <tr class="row-detail" id="det-{{ $rowId }}" data-detail-for="{{ $rowId }}" hidden>
                                <td colspan="7" class="detail-cell">
                                    <div class="detail-wrap">
                                        <div><strong>N° :</strong> {{ $row->num_int }}</div>
                                        <div><strong>Client :</strong> {{ $row->client }}</div>
                                        <div><strong>Marque :</strong> {{ $row->marque ?? '—' }}</div>
                                        <div><strong>Ville / CP :</strong> {{ $row->ville ?? '—' }} @if(!empty($row->cp)) ({{ $row->cp }}) @endif</div>
                                        <div class="full"><strong>Commentaire :</strong> {{ $row->commentaire ?? '—' }}</div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" style="text-align:center;color:var(--mut);padding:16px">Aucune intervention</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div id="pager" class="pager">
                    {{ $rows->onEachSide(1)->appends(['per_page'=>$perPage])->links('pagination.clean') }}
                </div>
            </div>
        </div>

        <div class="footer" style="display:flex;justify-content:space-between;align-items:center">
            <div class="meta" style="color:var(--mut);font-size:12px">
                Priorité serveur : (URGENT & VOUS) → URGENT → VOUS → Autre, puis date/heure.
                Cliquez les en-têtes pour trier côté navigateur.
            </div>
            <div>
                <a class="btn" href="{{ url()->previous() }}">Retour</a>
                <a class="btn" href="{{ route('interventions.show', ['per_page'=>$perPage]) }}">Actualiser</a>
            </div>
        </div>
    </div>

    {{-- JS : accordéon + navigation + historique lazy --}}
    <script>
        (function(){
            // 1) Accordéon (chevron)
            document.addEventListener('click', function(e){
                const t = e.target.closest('.js-row-toggle'); if (!t) return;
                const id = t.getAttribute('data-row-id');
                const det = document.getElementById('det-'+id);
                if (!det) return;
                const isOpen = !det.hasAttribute('hidden');
                if (isOpen) {
                    det.setAttribute('hidden','');
                    t.setAttribute('aria-expanded','false');
                    t.textContent = '▾';
                } else {
                    det.removeAttribute('hidden');
                    t.setAttribute('aria-expanded','true');
                    t.textContent = '▴';
                }
            });

            // 2) Navigation par clic sur la ligne (ignore la colonne Actions & boutons)
            document.getElementById('intervTable')?.addEventListener('click', function(e){
                if (e.target.closest('.col-actions, .js-row-toggle, .js-open, .js-open-history')) return;
                const tr = e.target.closest('tr.row[data-href]'); if (!tr) return;
                const href = tr.getAttribute('data-href'); if (href) window.location.href = href;
            });

            // 3) Historique lazy (ouvre une fenêtre et charge seulement au clic)
            document.addEventListener('click', async function(e){
                const btn = e.target.closest('.js-open-history'); if (!btn) return;
                const numInt = btn.getAttribute('data-num-int') || 'hist';
                const url    = btn.getAttribute('data-history-url');
                const win    = window.open('', 'historique_'+numInt, 'width=960,height=720');
                if (!win) return;

                try { win.document.open(); win.document.write('<p style="padding:12px;font:14px system-ui">Chargement…</p>'); win.document.close(); } catch(e){}

                try {
                    const res = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
                    const html = await res.text();
                    win.document.open();
                    win.document.write(html || '<p style="padding:12px;font:14px system-ui">Aucun contenu</p>');
                    win.document.close();
                } catch (err) {
                    win.document.open();
                    win.document.write('<p style="padding:12px;color:#a00">Erreur de chargement de l’historique.</p>');
                    win.document.close();
                }
            });
        })();
    </script>
@endsection
