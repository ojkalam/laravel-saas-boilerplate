<?php

use App\Models\Team;
use App\Support\CurrentTeam;
use App\Support\Plans\Plan;
use App\Support\Plans\PlanRegistry;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Billing settings')] class extends Component {
    public function mount(): void
    {
        abort_if($this->team() === null, 403);

        Gate::authorize('manageBilling', $this->team());
    }

    #[Computed]
    public function team(): ?Team
    {
        return app(CurrentTeam::class)->model();
    }

    #[Computed]
    public function plan(): Plan
    {
        return $this->team()->plan();
    }

    #[Computed]
    public function paidPlans(): array
    {
        return app(PlanRegistry::class)->all()
            ->reject(fn (Plan $plan) => $plan->isFree())
            ->values()
            ->all();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Billing')" :subheading="__('Manage your team\'s subscription')">
        <div class="my-6 space-y-6">
            <div>
                <flux:heading size="sm">{{ __('Current plan') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $this->plan->name }}
                    @if ($this->team->onGenericTrial())
                        — {{ __('trial ends :date', ['date' => $this->team->trial_ends_at->toFormattedDateString()]) }}
                    @endif
                </flux:text>
            </div>

            @if ($this->team->hasActiveSubscription())
                <flux:button :href="route('billing.portal')" variant="primary" data-test="billing-portal-button">
                    {{ __('Manage subscription') }}
                </flux:button>
                <flux:text class="text-sm">
                    {{ __('Plan changes, payment methods, invoices, and cancellation are handled in the Stripe billing portal.') }}
                </flux:text>
            @else
                <div class="space-y-3">
                    @foreach ($this->paidPlans as $plan)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div>
                                <flux:heading size="sm">{{ $plan->name }}</flux:heading>
                                <flux:text class="text-sm">${{ $plan->monthlyPrice }}/{{ __('month') }}{{ $plan->perSeat ? ' '.__('per seat') : '' }}</flux:text>
                            </div>
                            <div class="flex gap-2">
                                <flux:button size="sm" :href="route('billing.checkout', [$plan->key, 'monthly'])">{{ __('Monthly') }}</flux:button>
                                <flux:button size="sm" :href="route('billing.checkout', [$plan->key, 'yearly'])">{{ __('Yearly') }}</flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
