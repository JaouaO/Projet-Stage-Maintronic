@extends('layouts.base')

@section('title', 'Tickets/récap')

@section('content')

    <h1>Bienvenue, {{ $data->NomSal ?? 'Utilisateur' }}</h1>
    <h2>Page du tableau récap</h2>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

@endsection
