<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <title>{{ __('Pricing') }} · {{ config('app.name') }}</title>
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <div class="mx-auto max-w-5xl px-6 py-16">
            <div class="mb-12 text-center">
                <h1 class="text-4xl font-bold tracking-tight">{{ __('Simple pricing for every team') }}</h1>
                <p class="mt-3 text-zinc-600 dark:text-zinc-400">
                    {{ __('Start with a :days-day free trial. No credit card required.', ['days' => config('plans.trial_days')]) }}
                </p>
            </div>

            <div class="grid gap-8 md:grid-cols-3">
                @foreach ($plans as $plan)
                    <div class="flex flex-col rounded-2xl border border-zinc-200 p-8 dark:border-zinc-700 {{ $plan->key === 'pro' ? 'ring-2 ring-indigo-500' : '' }}">
                        <h2 class="text-lg font-semibold">{{ $plan->name }}</h2>

                        <p class="mt-4">
                            <span class="text-4xl font-bold">${{ $plan->monthlyPrice }}</span>
                            <span class="text-zinc-500">/ {{ __('month') }}{{ $plan->perSeat ? ' · '.__('per seat') : '' }}</span>
                        </p>

                        <ul class="mt-6 flex-1 space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                            <li>{{ $plan->isUnlimited('seats') ? __('Unlimited seats') : trans_choice(':count seat|:count seats', $plan->limit('seats') ?? 0, ['count' => $plan->limit('seats')]) }}</li>
                            <li>{{ $plan->isUnlimited('projects') ? __('Unlimited projects') : trans_choice(':count project|:count projects', $plan->limit('projects') ?? 0, ['count' => $plan->limit('projects')]) }}</li>
                            <li>{{ number_format($plan->limit('api_calls') ?? 0) }} {{ __('API calls / month') }}</li>
                            @if ($plan->allows('api'))<li>{{ __('API access') }}</li>@endif
                            @if ($plan->allows('audit_log'))<li>{{ __('Audit log') }}</li>@endif
                            @if ($plan->allows('sso'))<li>{{ __('SSO') }}</li>@endif
                        </ul>

                        <div class="mt-8">
                            @if ($plan->isFree())
                                <a href="{{ route('register') }}"
                                   class="block w-full rounded-lg border border-zinc-300 px-4 py-2 text-center text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800">
                                    {{ __('Get started') }}
                                </a>
                            @else
                                <a href="{{ auth()->check() ? route('billing.checkout', [$plan->key, 'monthly']) : route('register') }}"
                                   class="block w-full rounded-lg bg-indigo-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-indigo-500">
                                    {{ auth()->check() ? __('Subscribe') : __('Start free trial') }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </body>
</html>
