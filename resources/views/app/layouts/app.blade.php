{{--
    A bare default layout so the editor works out of the box. Most apps will
    point config('flowkit.layout') at their own layout instead; it needs a
    'content' section, @livewireStyles / @livewireScripts, and flowkit.css
    compiled through Tailwind.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('flowkit::editor.flows')) · {{ config('app.name') }}</title>
    <style>[x-cloak] { display: none !important; }</style>
    @livewireStyles
</head>
<body class="bg-[var(--flowkit-canvas)] antialiased">
    <div class="mx-auto max-w-7xl p-6">
        @yield('content')
    </div>

    @livewireScripts
</body>
</html>
