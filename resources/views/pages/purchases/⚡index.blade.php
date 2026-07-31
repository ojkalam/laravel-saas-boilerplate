<?php

use App\Actions\Marketplace\AuthorizeDownload;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Order;
use App\Support\CurrentTeam;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My purchases')] class extends Component {
    public function mount(): void
    {
        abort_if(app(CurrentTeam::class)->model() === null, 403);
    }

    /**
     * Free a seat so the buyer can move an install to another domain.
     */
    public function deactivate(int $activationId): void
    {
        $activation = LicenseActivation::query()
            ->whereKey($activationId)
            // The license query is team-scoped, so this cannot touch
            // another team's activation.
            ->whereIn('license_id', License::query()->select('id'))
            ->first();

        if ($activation === null) {
            abort(404);
        }

        $activation->delete();

        unset($this->licenses);

        Flux::toast(variant: 'success', text: __('Activation released.'));
    }

    public function downloadsRemaining(License $license): int
    {
        $limit = (int) config('marketplace.downloads.daily_limit');

        return max(0, $limit - app(AuthorizeDownload::class)->downloadsToday($license));
    }

    /**
     * Orders are team-scoped by the global scope, so this only ever
     * returns the current team's purchases.
     */
    #[Computed]
    public function orders(): Collection
    {
        return Order::query()
            ->with(['items.product'])
            ->latest()
            ->get();
    }

    #[Computed]
    public function licenses(): Collection
    {
        return License::query()
            ->with(['product.versions', 'activations'])
            ->latest()
            ->get();
    }
}; ?>

<section class="w-full">
    <div class="mb-8">
        <flux:heading size="xl">{{ __('My purchases') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('Licenses and downloads for :team', ['team' => auth()->user()->currentTeam?->name]) }}
        </flux:subheading>
    </div>

    @if (session('status'))
        <flux:callout variant="success" class="mb-6" data-test="purchase-status">
            {{ session('status') }}
        </flux:callout>
    @endif

    @if (session('error'))
        <flux:callout variant="danger" class="mb-6" data-test="purchase-error">
            {{ session('error') }}
        </flux:callout>
    @endif

    @if ($this->licenses->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 py-16 text-center dark:border-zinc-700" data-test="no-purchases">
            <flux:heading size="sm">{{ __('Nothing purchased yet') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Browse the marketplace to find themes and apps for your business.') }}</flux:text>
            <flux:button :href="route('marketplace.index')" variant="primary" size="sm" class="mt-4">
                {{ __('Browse the marketplace') }}
            </flux:button>
        </div>
    @else
        {{-- Licensed products --}}
        <div class="space-y-4">
            @foreach ($this->licenses as $license)
                <div
                    class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700"
                    wire:key="license-{{ $license->id }}"
                    data-test="license-row"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <flux:heading size="sm">{{ $license->product?->name ?? __('Removed product') }}</flux:heading>

                                @if ($license->isRevoked())
                                    <flux:badge size="sm" color="red">{{ __('Revoked') }}</flux:badge>
                                @elseif (! $license->hasUpdatesAccess())
                                    <flux:badge size="sm" color="amber">{{ __('Updates expired') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                @endif
                            </div>

                            <div class="mt-2 flex items-center gap-2">
                                <flux:text class="text-xs">{{ __('License key') }}</flux:text>
                                <code class="rounded bg-zinc-100 px-2 py-1 font-mono text-sm dark:bg-zinc-800" data-test="license-key">{{ $license->key }}</code>
                            </div>

                            <flux:text class="mt-2 text-xs">
                                @if ($license->expires_at)
                                    {{ $license->hasUpdatesAccess()
                                        ? __('Updates until :date', ['date' => $license->expires_at->toFormattedDateString()])
                                        : __('Updates ended :date', ['date' => $license->expires_at->toFormattedDateString()]) }}
                                    ·
                                @endif
                                {{ __(':used of :limit activations used', [
                                    'used' => $license->activations->count(),
                                    'limit' => $license->activation_limit,
                                ]) }}
                            </flux:text>
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($license->product)
                                <flux:button
                                    :href="route('marketplace.show', $license->product)"
                                    size="sm"
                                    variant="ghost"
                                >
                                    {{ __('View listing') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>

                    {{-- Releases --}}
                    @if ($license->isActive() && $license->product)
                        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <div class="flex items-center justify-between">
                                <flux:text class="text-xs font-medium">{{ __('Downloads') }}</flux:text>
                                <flux:text class="text-xs">
                                    {{ __(':count left today', ['count' => $this->downloadsRemaining($license)]) }}
                                </flux:text>
                            </div>

                            <div class="mt-2 space-y-2">
                                @foreach ($license->product->versions->sortByDesc('released_at') as $version)
                                    @php($allowed = $license->canDownload($version))

                                    <div class="flex items-center justify-between gap-3" wire:key="version-{{ $license->id }}-{{ $version->id }}">
                                        <flux:text class="text-sm">
                                            v{{ $version->version }}
                                            <span class="text-xs text-zinc-500">
                                                · {{ $version->released_at?->toFormattedDateString() }}
                                                · {{ $version->formattedFileSize() }}
                                            </span>
                                        </flux:text>

                                        @if ($allowed)
                                            <form method="POST" action="{{ route('downloads.create', [$license, $version]) }}">
                                                @csrf
                                                <flux:button
                                                    type="submit"
                                                    size="sm"
                                                    icon="arrow-down-tray"
                                                    data-test="download-{{ $version->id }}"
                                                >
                                                    {{ __('Download') }}
                                                </flux:button>
                                            </form>
                                        @else
                                            <flux:badge size="sm" color="zinc" data-test="download-locked-{{ $version->id }}">
                                                {{ __('Renew for access') }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Activations --}}
                    @if ($license->activations->isNotEmpty())
                        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <flux:text class="text-xs font-medium">{{ __('Active installs') }}</flux:text>

                            <div class="mt-2 space-y-2">
                                @foreach ($license->activations as $activation)
                                    <div class="flex items-center justify-between gap-3" wire:key="activation-{{ $activation->id }}">
                                        <flux:text class="text-sm">
                                            {{ $activation->instance }}
                                            <span class="text-xs text-zinc-500">
                                                · {{ $activation->activated_at->toFormattedDateString() }}
                                            </span>
                                        </flux:text>

                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            wire:click="deactivate({{ $activation->id }})"
                                            wire:confirm="{{ __('Release this activation?') }}"
                                            data-test="deactivate-{{ $activation->id }}"
                                        >
                                            {{ __('Release') }}
                                        </flux:button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Order history --}}
        <div class="mt-12">
            <flux:heading size="lg">{{ __('Order history') }}</flux:heading>

            <div class="mt-3 divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->orders as $order)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="order-{{ $order->id }}" data-test="order-row">
                        <div>
                            <flux:text class="font-mono text-sm">{{ $order->number }}</flux:text>
                            <flux:text class="text-xs">
                                {{ $order->created_at?->toFormattedDateString() }}
                                · {{ $order->items->pluck('product_name')->implode(', ') }}
                            </flux:text>
                        </div>

                        <div class="flex items-center gap-3">
                            <flux:badge
                                size="sm"
                                :color="match ($order->status->value) {
                                    'paid' => 'green',
                                    'refunded' => 'amber',
                                    'failed' => 'red',
                                    default => 'zinc',
                                }"
                            >
                                {{ $order->status->label() }}
                            </flux:badge>
                            <span class="font-medium">{{ $order->formattedTotal() }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
