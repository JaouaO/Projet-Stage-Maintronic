<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Mon Projet')</title>
    <link rel="stylesheet" href="{{ asset('css/interventions.css') }}">
</head>
<body>
<header>
    @if(session()->has('id'))
        <p class="header-user">
            Connecté  |
            <a href="{{ route('deconnexion') }}">Se déconnecter</a>
        </p>
    @else
        <p class="header-user">Non connecté</p>
    @endif
</header>

<main>
    @yield('content')
</main>
</body>
</html>
