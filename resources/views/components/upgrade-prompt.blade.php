@props(['feature' => null, 'title' => null])

{{--
    Shown in place of a gated feature. Usage:

    @feature('audit_log')
        ... the feature ...
    @else
        <x-upgrade-prompt :title="__('Audit log')" />
    @endfeature
--}}
<div {{ $attributes->merge(['class' => 'rounded-xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-600']) }}>
    <flux:heading size="sm">
        {{ $title ?? __('This feature is not on your plan') }}
    </flux:heading>

    <flux:text class="mt-2 text-sm">
        {{ $slot->isEmpty() ? __('Upgrade your plan to unlock this feature for your team.') : $slot }}
    </flux:text>

    <flux:button :href="route('billing.edit')" variant="primary" size="sm" class="mt-4" wire:navigate>
        {{ __('View plans') }}
    </flux:button>
</div>
