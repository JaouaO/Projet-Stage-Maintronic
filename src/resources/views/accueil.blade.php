@extends('layouts.base')

@section('title', 'Accueil')

@section('content')

    <h1>Bienvenue, {{ $data->NomSal ?? 'Utilisateur' }}</h1>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

@endsection
