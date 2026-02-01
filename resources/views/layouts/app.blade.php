<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'CleanMyData') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased text-slate-700 relative overflow-x-hidden flex flex-col min-h-screen">
    

    <div class="fixed inset-0 -z-10">
        <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-sky-300/40 rounded-full blur-3xl"></div>
        <div class="absolute top-[20%] right-[-10%] w-[600px] h-[600px] bg-indigo-300/30 rounded-full blur-3xl"></div>
        <div class="absolute bottom-[-10%] left-[20%] w-[500px] h-[500px] bg-sky-200/40 rounded-full blur-3xl"></div>
    </div>
    

    <div class="h-1 bg-gradient-to-r from-sky-400 via-sky-500 to-indigo-400"></div>

    @include('layouts.navigation')

    

    @isset($header)
        <header class="relative -z-10 bg-white/50 backdrop-blur-lg shadow-sm">
            <div class="max-w-7xl mx-auto py-6 px-6">
                <h1 class="text-3xl font-bold text-slate-800">{{ $header }}</h1>
                <p class="mt-2 text-slate-500 max-w-2xl">
                    Clean, analyze and improve your datasets with confidence
                </p>
            </div>
        </header>
    @endisset

    <main class="flex-1">
        <div class="max-w-7xl mx-auto px-6 py-6">
            <div class="relative bg-white/80 backdrop-blur-xl rounded-3xl shadow-xl p-8 border border-white/50">
                <div class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-sky-400 to-indigo-400 rounded-t-3xl"></div>
                {{ $slot }}
            </div>
        </div>
    </main>

    <footer class="bg-white/70 backdrop-blur border-t mt-auto">
        <div class="max-w-7xl mx-auto px-6 py-10 flex flex-col md:flex-row items-center justify-between gap-6 text-sm text-slate-500">
            <div>
                &copy; {{ date('Y') }}
                <span class="font-semibold text-slate-700">CleanMyData</span>.
                All rights reserved.
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
