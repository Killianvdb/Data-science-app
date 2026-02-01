<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $header ?? config('app.name', 'CleanMyData') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen font-sans antialiased flex flex-col">

    <!-- GLOBAL BACKGROUND BLUR GRADIENTS -->
    <div class="fixed inset-0 -z-10">
        <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-sky-300/40 rounded-full blur-3xl"></div>
        <div class="absolute top-[20%] right-[-10%] w-[600px] h-[600px] bg-indigo-300/30 rounded-full blur-3xl"></div>
        <div class="absolute bottom-[-10%] left-[20%] w-[500px] h-[500px] bg-sky-200/40 rounded-full blur-3xl"></div>
    </div>

    <!-- TOP ACCENT BAR -->
    <div class="h-1 bg-gradient-to-r from-sky-400 via-sky-500 to-indigo-400"></div>

    <!-- NAVBAR -->
    @include('layouts.navigation')

    <!-- PAGE HEADER (Login/Register title) -->
    @isset($header)
        <header class="max-w-7xl mx-auto py-6 px-6 text-center">
            <h1 class="text-3xl font-bold text-slate-800">{{ $header }}</h1>
        </header>
    @endisset

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md bg-white/80 backdrop-blur-xl rounded-3xl shadow-xl p-8 border border-white/50">
            {{ $slot }}
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="bg-white/70 backdrop-blur border-t border-white/50 mt-auto">
        <div class="max-w-7xl mx-auto px-6 py-6 flex flex-col md:flex-row items-center justify-between text-sm text-gray-500 gap-4">
            <div>
                &copy; {{ date('Y') }} <span class="font-semibold text-slate-700">CleanMyData</span>. All rights reserved.
            </div>
            <div class="flex gap-6">
                <a href="{{ route('how-it-works') }}" class="hover:text-sky-600 transition">How it works</a>
                <a href="{{ route('privacy.policy') }}" class="hover:text-sky-600 transition">Privacy</a>
                <a href="{{ route('contact') }}" class="hover:text-sky-600 transition">Contact</a>
            </div>
        </div>
    </footer>

</body>
</html>
