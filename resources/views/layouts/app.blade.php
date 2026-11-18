<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="top">
        <a class="brand" href="{{ url('/') }}">{{ config('app.name') }}</a>
        @auth
            <nav>
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a href="{{ route('onboarding') }}">Company</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="link">Sign out</button></form>
            </nav>
        @endauth
    </header>
    <main>{{ $slot }}</main>
    <footer>This is a tool, not tax or accounting advice.</footer>
</body>
</html>
