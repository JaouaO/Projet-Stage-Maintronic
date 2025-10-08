@extends('layouts.base')

@section('title', 'Accueil')

@section('content')
    <h1>Bienvenue, {{ $data->NomSal ?? 'Utilisateur' }}</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('accueil.entree', ['id' => session('id')]) }}" autocomplete="off">
        @csrf

        <div class="form-group">
            <label for="num_int">Numéro d’intervention</label>
            <input id="num_int" name="num_int" class="form-control" list="numint-list"
                   placeholder="Tapez ou choisissez…" value="{{ old('num_int') }}" required>
            <datalist id="numint-list">
                @foreach($numints as $n)
                    <option value="{{ $n }}">{{ $n }}</option>
                @endforeach
            </datalist>
            <small class="form-text text-muted">Saisie assistée : liste filtrée pendant la frappe.</small>
        </div>

        <div class="form-group mt-3">
            <label for="agence">Agence</label>
            <select id="agence" name="agence" class="form-control" required>
                @php $agences = $data->agences_autorisees ?? []; @endphp
                @forelse($agences as $ag)
                    <option value="{{ $ag }}" @selected(old('agence')===$ag)>{{ $ag }}</option>
                @empty
                    <option value="" disabled>Aucune agence autorisée</option>
                @endforelse
            </select>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Valider</button>
        </div>
    </form>
@endsection
