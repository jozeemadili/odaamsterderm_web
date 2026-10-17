<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'DukaFlow' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon-180.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas">
    <div class="gradient-mesh min-h-screen flex flex-col">
        <header class="px-8 py-6 flex items-center gap-2.5">
            <img src="{{ asset('images/favicon-512.png') }}" alt="DukaFlow" class="h-8 w-8 rounded-md">
            <span class="text-[20px] font-light tracking-tight text-ink">Duka<span class="font-medium text-primary">Flow</span></span>
        </header>

        <main class="flex-1 flex items-center justify-center px-4 pb-16">
            <div class="w-full max-w-md">
                <div class="text-center mb-6">
                    <p class="text-[13px] uppercase tracking-wide text-ink-mute">Tanzania SME financial operating system</p>
                </div>
                <div class="bg-canvas/95 backdrop-blur rounded-xl border border-hairline shadow-[0_8px_24px_rgba(0,55,112,0.08),0_2px_6px_rgba(0,55,112,0.04)] p-8">
                    {{ $slot }}
                </div>
            </div>
        </main>
    </div>
    @livewireScripts
</body>
</html>
