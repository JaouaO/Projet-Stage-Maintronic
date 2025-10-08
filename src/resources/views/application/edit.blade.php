@extends('layouts.base')

@section('title', 'Intervention/appel')

@section('content')

    <h1>Bienvenue, {{ $data->NomSal ?? 'Utilisateur' }}</h1>
    <h2>Page d'édition</h2>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

@endsection
