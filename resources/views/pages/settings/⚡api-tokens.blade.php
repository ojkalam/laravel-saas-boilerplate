<?php

use App\Models\Team;
use App\Support\CurrentTeam;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API tokens')] class extends Component {
    public string $tokenName = '';

    public ?string $plainTextToken = null;

    public function mount(): void
    {
        abort_if($this->team() === null, 403);
    }

    #[Computed]
    public function team(): ?Team
    {
        return app(CurrentTeam::class)->model();
    }

    #[Computed]
    public function tokens()
    {
        return Auth::user()->tokens()
            ->get()
            ->filter(fn ($token) => in_array('team:'.$this->team()->id, $token->abilities ?? [], true))
            ->values();
    }

    public function createToken(): void
    {
        $validated = $this->validate(['tokenName' => ['required', 'string', 'max:255']]);

        $token = Auth::user()->createToken(
            $validated['tokenName'],
            ['team:'.$this->team()->id],
        );

        $this->plainTextToken = $token->plainTextToken;
        $this->reset('tokenName');
        unset($this->tokens);
    }

    public function revokeToken(int $tokenId): void
    {
        Auth::user()->tokens()->whereKey($tokenId)->delete();

        unset($this->tokens);

        Flux::toast(variant: 'success', text: __('Token revoked.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('API tokens')" :subheading="__('Tokens are scoped to your current team')">
        <div class="my-6 space-y-8">
            @if ($plainTextToken)
                <div class="rounded-lg border border-green-300 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950">
                    <flux:text class="text-sm font-medium">{{ __('Copy your new token now — it will not be shown again.') }}</flux:text>
                    <code class="mt-2 block break-all text-sm" data-test="plain-text-token">{{ $plainTextToken }}</code>
                </div>
            @endif

            <form wire:submit="createToken" class="flex items-end gap-3">
                <div class="flex-1">
                    <flux:input wire:model="tokenName" :label="__('Token name')" required data-test="token-name-input" />
                </div>
                <flux:button variant="primary" type="submit" data-test="create-token-button">
                    {{ __('Create token') }}
                </flux:button>
            </form>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->tokens as $token)
                    <div class="flex items-center justify-between py-3" wire:key="token-{{ $token->id }}">
                        <div>
                            <flux:text class="font-medium">{{ $token->name }}</flux:text>
                            <flux:text class="text-sm">
                                {{ __('Last used') }}: {{ $token->last_used_at?->diffForHumans() ?? __('never') }}
                            </flux:text>
                        </div>
                        <flux:button
                            size="sm"
                            variant="danger"
                            wire:click="revokeToken({{ $token->id }})"
                            wire:confirm="{{ __('Revoke this token?') }}"
                            data-test="revoke-token-{{ $token->id }}"
                        >
                            {{ __('Revoke') }}
                        </flux:button>
                    </div>
                @empty
                    <flux:text class="py-3 text-sm">{{ __('No API tokens for this team yet.') }}</flux:text>
                @endforelse
            </div>
        </div>
    </x-pages::settings.layout>
</section>
