<header class="mb-6 flex justify-between items-center">
    <!-- Left side: Logo and other links -->
    <div class="flex items-center gap-2">
        <a href="{{ route('home') }}">
            <x-app-logo-icon class="size-8" />
        </a>
        <a href="{{ route('chatgpt') }}" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
            ChatGPT
        </a>
        <a href="{{ route('mcp') }}" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
            MCP
        </a>
    </div>

    <!-- Right side: Auth-related links -->
    <nav class="flex items-center gap-2 sm:gap-4 flex-wrap">
        @auth
            <a href="{{ url('/dashboard') }}" class="inline-flex items-center gap-2 rounded-md border border-black/10 bg-white px-3 py-2 text-sm font-medium text-black hover:bg-gray-50 dark:border-white/10 dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700">
                Dashboard
            </a>
        @else
            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                Log in
            </a>
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-md border border-black/10 bg-white px-3 py-2 text-sm font-medium text-black hover:bg-gray-50 dark:border-white/10 dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700">
                    Register
                </a>
            @endif
        @endauth
    </nav>
</header>
