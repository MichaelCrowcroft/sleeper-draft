@props(['title' => null, 'description' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => $title, 'description' => $description])
        @stack('head')
    </head>
    <body class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-slate-50 dark:from-zinc-900 dark:via-zinc-950 dark:to-zinc-900 text-zinc-900 dark:text-zinc-100">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-8">
            @include('partials.marketing-nav')

            {{ $slot }}
        </div>

        @fluxScripts
    </body>
</html>
