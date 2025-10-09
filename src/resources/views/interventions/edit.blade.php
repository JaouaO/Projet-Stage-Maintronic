@extends('layouts.base')
@section('title', 'Intervention')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div style="max-width:980px;margin:16px auto;padding:12px;">

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h1 style="margin:0;font:600 18px/1.2 system-ui,Segoe UI">
                Intervention {{ $interv->NumInt }}
            </h1>
            <div style="color:#657089;font-size:13px">
                Utilisateur : {{ $data->NomSal ?? ($data->CodeSal ?? '—') }}
            </div>
        </div>

        {{-- NOTE INTERNE (inline/ AJAX) --}}
        <div style="border:1px solid #e5e7eb;border-radius:8px;background:#fff;margin-bottom:12px;">
            <div style="padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f7faff">
                <strong>Note interne</strong>
            </div>
            <div style="padding:12px">
                <div id="noteInterne"
                     data-update-url="{{ route('interventions.note.update', ['numInt' => $interv->NumInt]) }}"
                     style="min-height:80px;border:1px dashed #d1d5db;border-radius:6px;padding:8px;white-space:pre-wrap;cursor:text;background:#fff;">
                    {{ $interv->CommentInterneTxt ?? '' }}
                </div>

                <div style="display:flex;gap:8px;margin-top:8px;">
                    <button id="btnEdit"    type="button" style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px;background:#fff">Modifier</button>
                    <button id="btnSave"    type="button" style="padding:6px 10px;border:1px solid #cfe0ff;border-radius:6px;background:#e8f1ff;display:none;">Enregistrer</button>
                    <button id="btnCancel"  type="button" style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;display:none;">Annuler</button>
                    <span id="noteStatus"   style="margin-left:auto;font-size:12px;color:#657089;"></span>
                </div>
            </div>
        </div>

        {{-- HISTORIQUE SERVEUR (lecture seule) --}}
        <div style="border:1px solid #e5e7eb;border-radius:8px;background:#fff;">
            <div style="padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f7faff">
                <strong>Historique serveur (automatique)</strong>
            </div>

            @if(isset($suivis) && $suivis->count())
                <ul style="list-style:none;margin:0;padding:0;">
                    @foreach($suivis as $s)
                        <li style="padding:10px 12px;border-bottom:1px solid #f1f3f5">
                            @php
                                $dateTxt = '—';
                                if ($s->CreatedAt) {
                                    try {
                                        $dt = \Carbon\Carbon::parse($s->CreatedAt);
                                        // Si c'est un DATETIME → on affiche date + heure ; si DATE → juste la date.
                                        $dateTxt = $dt->format( $dt->toTimeString() !== '00:00:00' ? 'd/m/Y H:i' : 'd/m/Y' );
                                    } catch (\Exception $e) { $dateTxt = '—'; }
                                }
                            @endphp
                            <div style="font-size:12px;color:#6b7280;margin-bottom:4px">
                                {{ $dateTxt }}
                                @if($s->CodeSalAuteur) — {{ $s->CodeSalAuteur }} @endif
                                @if($s->Titre) — <strong>{{ $s->Titre }}</strong> @endif
                            </div>
                            <div style="white-space:pre-wrap;">{{ $s->Texte }}</div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div style="padding:12px;color:#657089">Aucun suivi pour ce dossier.</div>
            @endif
        </div>

        {{-- Flashs (si besoin) --}}
        @if(session('success'))
            <div style="margin-top:12px;padding:8px;border:1px solid #cfe0ff;background:#e8f1ff;border-radius:6px;color:#143b7a">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div style="margin-top:12px;padding:8px;border:1px solid #f7caca;background:#ffecec;border-radius:6px;color:#7a1d27">
                {{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div style="margin-top:12px;padding:8px;border:1px solid #f7caca;background:#ffecec;border-radius:6px;color:#7a1d27">
                <ul style="margin:0;padding-left:18px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- expose l'id session pour satisfaire le middleware en POST --}}
    <script>window.APP_SESSION_ID = "{{ session('id') }}";</script>
    <script src="{{ asset('js/intervention_note.js') }}" defer></script>
@endsection
