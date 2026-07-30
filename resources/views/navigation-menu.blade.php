<nav x-data="{ open: false, settingsOpen: false }" class="bg-white dark:bg-bambu-dark-2 border-b border-gray-100 dark:border-bambu-dark-4 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">

            {{-- Logo + Links --}}
            <div class="flex items-center space-x-8">
                <a href="{{ route('dashboard') }}" class="shrink-0 flex items-center">
                    <img src="/images/bambups_logo.png" class="h-10 w-auto rounded logo-light" alt="BambuPS">
                    <img src="/images/bambups_logob.png" class="h-10 w-auto rounded logo-dark" alt="BambuPS">
                </a>

                <div class="hidden sm:flex items-center space-x-1">
                    @php
                        $navLink = fn($route, $label, $active = null) =>
                            '<a href="' . route($route) . '" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors ' .
                            (request()->routeIs($active ?? $route)
                                ? 'bg-green-50 text-green-700 dark:bg-bambu-dark-3 dark:text-bambu-green'
                                : 'text-gray-600 dark:text-bambu-text-dim hover:bg-gray-50 dark:hover:bg-bambu-dark-3 hover:text-gray-900 dark:hover:text-bambu-text'
                            ) . '">' . $label . '</a>';
                    @endphp

                    {!! $navLink('dashboard', 'Dashboard') !!}
                    {!! $navLink('printers', '🖨️ Tiskárny', 'printers*') !!}
                    {!! $navLink('files', '📁 Soubory', 'files*') !!}

                    {{-- Nastavení dropdown --}}
                    <div class="relative">
                        <button @click="settingsOpen = !settingsOpen" @click.outside="settingsOpen = false"
                            class="flex items-center space-x-1 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                                {{ request()->routeIs('*.settings') || request()->routeIs('printers.manage') || request()->routeIs('notifications.*')
                                    ? 'bg-green-50 text-green-700 dark:bg-bambu-dark-3 dark:text-bambu-green'
                                    : 'text-gray-600 dark:text-bambu-text-dim hover:bg-gray-50 dark:hover:bg-bambu-dark-3 hover:text-gray-900 dark:hover:text-bambu-text' }}">
                            <span>⚙️ Nastavení</span>
                            <svg class="size-3.5 transition-transform" :class="{ 'rotate-180': settingsOpen }"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div x-show="settingsOpen" x-transition
                            class="absolute left-0 mt-1 w-52 bg-white dark:bg-bambu-dark-3 rounded-xl shadow-xl border border-gray-100 dark:border-bambu-dark-4 py-1.5 z-50">
                            <a href="{{ route('printers.manage') }}"
                                class="flex items-center space-x-2.5 px-4 py-2.5 text-sm text-gray-700 dark:text-bambu-text hover:bg-gray-50 dark:hover:bg-bambu-dark-4 {{ request()->routeIs('printers.manage') ? 'font-semibold text-green-700 dark:text-bambu-green' : '' }}">
                                <span>🖨️</span><span>Správa tiskáren</span>
                            </a>
                            <a href="{{ route('notifications.settings') }}"
                                class="flex items-center space-x-2.5 px-4 py-2.5 text-sm text-gray-700 dark:text-bambu-text hover:bg-gray-50 dark:hover:bg-bambu-dark-4 {{ request()->routeIs('notifications.settings') ? 'font-semibold text-green-700 dark:text-bambu-green' : '' }}">
                                <span>🔔</span><span>Notifikace</span>
                            </a>
                            <a href="{{ route('modules.settings') }}"
                                class="flex items-center space-x-2.5 px-4 py-2.5 text-sm text-gray-700 dark:text-bambu-text hover:bg-gray-50 dark:hover:bg-bambu-dark-4 {{ request()->routeIs('modules.settings') ? 'font-semibold text-green-700 dark:text-bambu-green' : '' }}">
                                <span>🧩</span><span>Moduly</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right side --}}
            <div class="hidden sm:flex items-center space-x-2">
		<livewire:version-badge />
                {{-- Dark mode toggle --}}
                <div x-data="{ dark: localStorage.getItem('darkMode') === 'true' }">
                    <button @click="dark = !dark; localStorage.setItem('darkMode', dark); document.documentElement.classList.toggle('dark', dark)"
                        class="p-2 rounded-lg text-gray-500 dark:text-bambu-text-dim hover:bg-gray-100 dark:hover:bg-bambu-dark-3 transition-colors text-base">
                        <span x-show="!dark">🌙</span>
                        <span x-show="dark">☀️</span>
                    </button>
                </div>

                {{-- User dropdown --}}
                <div class="relative" x-data="{ userOpen: false }">
                    <button @click="userOpen = !userOpen" @click.outside="userOpen = false"
                        class="flex items-center space-x-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-bambu-text-dim hover:bg-gray-50 dark:hover:bg-bambu-dark-3 transition-colors">
                        <div class="w-7 h-7 rounded-full bg-green-100 dark:bg-bambu-dark-4 flex items-center justify-center text-green-700 dark:text-bambu-green font-bold text-xs">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <span>{{ Auth::user()->name }}</span>
                        <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div x-show="userOpen" x-transition
                        class="absolute right-0 mt-1 w-48 bg-white dark:bg-bambu-dark-3 rounded-xl shadow-xl border border-gray-100 dark:border-bambu-dark-4 py-1.5 z-50">
                        <div class="px-4 py-2 text-xs text-gray-400 dark:text-bambu-text-dim border-b border-gray-100 dark:border-bambu-dark-4">
                            {{ Auth::user()->email }}
                        </div>
                        <a href="{{ route('profile.show') }}"
                            class="flex items-center space-x-2.5 px-4 py-2.5 text-sm text-gray-700 dark:text-bambu-text hover:bg-gray-50 dark:hover:bg-bambu-dark-4">
                            <span>👤</span><span>Profil</span>
                        </a>
                        <div class="border-t border-gray-100 dark:border-bambu-dark-4 my-1"></div>
                        <form method="POST" action="{{ route('logout') }}" x-data>
                            @csrf
                            <button @click.prevent="$root.submit()"
                                class="w-full flex items-center space-x-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-bambu-dark-4">
                                <span>🚪</span><span>Odhlásit se</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Hamburger --}}
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = !open"
                    class="p-2 rounded-lg text-gray-400 hover:text-gray-500 hover:bg-gray-100 transition-colors">
                    <svg class="size-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': !open}" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': !open, 'inline-flex': open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div :class="{'block': open, 'hidden': !open}" class="hidden sm:hidden border-t border-gray-100 dark:border-bambu-dark-4">
        <div class="p-3 space-y-1">
	{{-- Dark mode toggle --}}
            <div x-data="{ dark: localStorage.getItem('darkMode') === 'true' }"
                class="flex items-center justify-between px-3 py-2">
                <span class="text-sm font-medium text-gray-600 dark:text-bambu-text">Režim zobrazení</span>
                <button @click="dark = !dark; localStorage.setItem('darkMode', dark); document.documentElement.classList.toggle('dark', dark)"
                    class="p-2 rounded-lg text-gray-500 dark:text-bambu-text-dim hover:bg-gray-100 dark:hover:bg-bambu-dark-3 transition-colors text-base">
                    <span x-show="!dark">🌙</span>
                    <span x-show="dark">☀️</span>
                </button>
            </div>
        <a href="{{ route('dashboard') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-bambu-text hover:bg-gray-50 dark:hover:bg-bambu-dark-3">Dashboard</a>
            <a href="{{ route('printers') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-bambu-text hover:bg-gray-50 dark:hover:bg-bambu-dark-3">🖨️ Tiskárny</a>
            <a href="{{ route('files') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-bambu-text hover:bg-gray-50 dark:hover:bg-bambu-dark-3">📁 Soubory</a>
            <a href="{{ route('printers.manage') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-bambu-text hover:bg-gray-50 dark:hover:bg-bambu-dark-3">⚙️ Správa tiskáren</a>
            <a href="{{ route('notifications.settings') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-bambu-text hover:bg-gray-50 dark:hover:bg-bambu-dark-3">🔔 Notifikace</a>
            <a href="{{ route('modules.settings') }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-bambu-text hover:bg-gray-50 dark:hover:bg-bambu-dark-3">🧩 Moduly</a>
        </div>
        <div class="border-t border-gray-100 dark:border-bambu-dark-4 p-3 space-y-1">
            <a href="{{ route('profile.show') }}" class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-bambu-text hover:bg-gray-50 dark:hover:bg-bambu-dark-3">👤 Profil</a>
            <form method="POST" action="{{ route('logout') }}" x-data>
                @csrf
                <button @click.prevent="$root.submit()" class="w-full text-left px-3 py-2 rounded-lg text-sm text-red-600 hover:bg-red-50">🚪 Odhlásit se</button>
            </form>
        </div>
    </div>
</nav>
