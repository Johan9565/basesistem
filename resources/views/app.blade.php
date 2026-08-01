<!DOCTYPE html>
@php
    $activeTheme = $activeTheme ?? 'dark';
    $darkThemes = ['dark', 'forest', 'business', 'cyberpunk', 'custom'];
    $isDark = in_array($activeTheme, $darkThemes);
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $activeTheme }}"
    @if ($isDark) class="dark" @endif>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">

    <title inertia>{{ config('app.name', 'Small Animal Clinic') }}</title>
    <!-- Favicon & Logo para Navegador y Buscadores -->
    <link rel="icon" type="image/png" href="{{ asset('images/logo_without_name.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo_without_name.png') }}">

    <meta property="og:site_name" content="{{ config('app.name', 'Small Animal Clinic') }}" />
    <meta property="og:image" content="{{ asset('images/logo_without_name.png') }}" />
    <meta name="twitter:image" content="{{ asset('images/logo_without_name.png') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body class="font-sans antialiased ">
    @php $theme = $componentTheme ?? []; @endphp
    @if (!empty($theme))
        <style id="component-theme">
            :root {
                @foreach ($theme as $var => $val)
                    @if ($var && $val !== null && $val !== '')
                        {{ $var }}: {{ $val }};
                    @endif
                @endforeach
            }
        </style>
    @endif
    @inertia
</body>

</html>
