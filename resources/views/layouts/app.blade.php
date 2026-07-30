<!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
        x-data="{ dark: localStorage.getItem('darkMode') === 'true' }"
        x-init="$watch('dark', v => { localStorage.setItem('darkMode', v); document.documentElement.classList.toggle('dark', v); }); document.documentElement.classList.toggle('dark', dark);"
        :class="{ 'dark': dark }">
    <head>
        {{-- Nastavit tmavý režim OKAMŽITĚ, ještě před vykreslením čehokoliv -
        Alpine.js (x-init níže) se spustí až po načtení @vite bundle, což je
        moc pozdě a způsobuje krátké "problikávání" světlým tématem při
        každém načtení stránky. Tenhle blokující skript to řeší. --}}
        <script>
            if (localStorage.getItem('darkMode') === 'true') {
                document.documentElement.classList.add('dark');
            }
        </script>
	<link rel="icon" type="image/png" href="/images/bambups_logob.png">
	<link rel="apple-touch-icon" href="/images/bambups_logob.png">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <script src="/js/video-rtc.js"></script>
    </head>
    <body class="font-sans antialiased bg-gray-100 dark:bg-bambu-dark text-gray-900 dark:text-bambu-text">
        <x-banner />
        <div class="min-h-screen bg-gray-100">
            @livewire('navigation-menu')
            @if (isset($header))
		<header class="bg-white dark:bg-bambu-dark-2 border-b border-gray-100 dark:border-bambu-dark-4">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif
            <main>
                {{ $slot }}
            </main>
        </div>

        {{-- Toast kontejner --}}
        <div id="toast-container" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none;"></div>

        @stack('modals')
        @livewireScripts

        <script>
            window.addEventListener('toast', function (e) {
                const type    = e.detail.type    ?? e.detail[0]?.type    ?? 'success';
                const message = e.detail.message ?? e.detail[0]?.message ?? '';

                const container = document.getElementById('toast-container');
                const toast     = document.createElement('div');

                toast.style.cssText = 'padding:12px 20px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);font-size:14px;font-weight:500;display:flex;align-items:center;gap:8px;opacity:0;transform:translateY(16px);transition:opacity 0.3s,transform 0.3s;';
                toast.style.background = type === 'success' ? '#16a34a' : '#dc2626';
                toast.style.color      = '#fff';
                toast.innerHTML        = (type === 'success' ? '✅ ' : '❌ ') + message;

                container.appendChild(toast);

                requestAnimationFrame(() => {
                    toast.style.opacity   = '1';
                    toast.style.transform = 'translateY(0)';
                });

                setTimeout(() => {
                    toast.style.opacity   = '0';
                    toast.style.transform = 'translateY(16px)';
                    setTimeout(() => toast.remove(), 300);
                }, 3500);
            });
        </script>
	<!-- Footer -->
        <footer class="mt-auto py-4 text-center text-xs text-gray-400 dark:text-bambu-text-dim border-t border-gray-100 dark:border-bambu-dark-4">
            <span>BambuPS v1.0 &nbsp;·&nbsp; Autor: <a href="#" class="hover:text-green-600 transition-colors">David Trubka</a> &nbsp;·&nbsp; © 2026</span>
        </footer>
    </body>
</html>
