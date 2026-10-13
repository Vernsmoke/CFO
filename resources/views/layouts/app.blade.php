<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#150f23">
    <title>@yield('title', 'CFO Management System')</title>
    <link rel="icon" href="{{ asset('images/cfo-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <script src="{{ asset('app.js') }}" defer></script>
</head>
<body>
@yield('body')
</body>
</html>
