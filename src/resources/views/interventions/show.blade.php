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
                            <col style="width:160px">  {{-- NumInt --}}
                            <col style="width:320px">  {{-- Client --}}
                            <col style="width:120px">  {{-- Date --}}
                            <col style="width:90px">   {{-- Heure --}}
                            <col style="width:300px">  {{-- À faire --}}
                            <col style="width:140px">  {{-- Badges (sans titre) --}}
                            <col>                      {{-- Actions --}}
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
                                $isUrgent = isset($row->urgent) && (int)$row->urgent === 1;
                                $isMe     = isset($row->concerne) && (int)$row->concerne === 1;

                                // À faire via vocab (code/label) si dispo, sinon texte brut
                                $affCode  = $row->a_faire_code ?? null;   // ex: RDV_A_FIXER
                                $affLabel = $row->a_faire_label ?? ($row->a_faire ?? 'À préciser');
                                $tagClass = $affCode ? 't-'.$affCode : 'autre';

                                // Classe de ligne pour l'accent (inset shadow)
                                $trClass = $isUrgent && $isMe ? 'row-urgent-me' : ($isUrgent ? 'row-urgent' : ($isMe ? 'row-me' : ''));
                            @endphp

                            <tr class="row {{ $trClass }}" data-href="{{ route('interventions.edit', ['numInt' => $row->num_int]) }}">
                                <td class="col-id">{{ $row->num_int }}</td>
                                <td class="col-client">{{ $row->client }}</td>
                                <td class="col-date">
                                    {{ $row->date_prev ? \Carbon\Carbon::parse($row->date_prev)->format('d/m/Y') : '—' }}
                                </td>
                                <td class="col-heure">
                                    {{ $row->heure_prev ? \Carbon\Carbon::parse($row->heure_prev)->format('H:i') : '—' }}
                                </td>

                                <td class="col-todo">
                                    <span class="tag {{ $tagClass }}">{{ $affLabel }}</span>
                                </td>

                                {{-- Colonne badges (sans titre) --}}
                                <td class="col-flags">
                                    @if($isUrgent)
                                        <span class="badge badge-urgent">URGENT</span>
                                    @endif
                                    @if($isMe)
                                        <span class="badge {{ $isUrgent ? 'badge-me-over-urgent' : 'badge-me' }}">VOUS</span>
                                    @endif
                                </td>

                                <td class="col-actions">
                                    <a class="btn" href="{{ route('interventions.edit', ['numInt' => $row->num_int]) }}">Ouvrir</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align:center;color:var(--mut);padding:16px">
                                    Aucune intervention
                                </td>
                            </tr>
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
                Priorité serveur : (URGENT & VOUS) → URGENT → VOUS → Autre, puis date/heure. Cliquez les en-têtes pour trier côté navigateur.
            </div>
            <div>
                <a class="btn" href="{{ url()->previous() }}">Retour</a>
                <a class="btn" href="{{ route('interventions.show', ['per_page'=>$perPage]) }}">Actualiser</a>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/interventions.js') }}" defer></script>
@endsection
