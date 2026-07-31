<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <header class="border-b border-zinc-200 dark:border-zinc-800">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
                <a href="{{ route('marketplace.index') }}" class="flex items-center gap-2 font-semibold" wire:navigate>
                    <x-app-logo-icon class="size-7" />
                    <span>{{ __('Marketplace') }}</span>
                </a>

                <nav class="hidden items-center gap-6 text-sm md:flex">
                    <a href="{{ route('marketplace.index', ['type' => 'theme']) }}" class="hover:underline" wire:navigate>{{ __('Themes') }}</a>
                    <a href="{{ route('marketplace.index', ['type' => 'app']) }}" class="hover:underline" wire:navigate>{{ __('Apps') }}</a>
                    <a href="{{ route('pricing') }}" class="hover:underline">{{ __('Pricing') }}</a>
                </nav>

                <div class="flex items-center gap-3 text-sm">
                    @auth
                        @if (Route::has('purchases.index'))
                            <a href="{{ route('purchases.index') }}" class="hover:underline" wire:navigate>{{ __('My purchases') }}</a>
                        @endif
                        <flux:button :href="route('dashboard')" size="sm" variant="primary">{{ __('Dashboard') }}</flux:button>
                    @else
                        <a href="{{ route('login') }}" class="hover:underline">{{ __('Log in') }}</a>
                        <flux:button :href="route('register')" size="sm" variant="primary">{{ __('Get started') }}</flux:button>
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-10">
            {{ $slot }}
        </main>

        <footer class="mt-16 border-t border-zinc-200 py-8 text-center text-sm text-zinc-500 dark:border-zinc-800">
            &copy; {{ now()->year }} {{ config('app.name') }}
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
