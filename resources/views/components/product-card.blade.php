@props(['product'])

<a
    href="{{ route('marketplace.show', $product) }}"
    wire:navigate
    class="group flex flex-col overflow-hidden rounded-xl border border-zinc-200 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:hover:border-zinc-600"
    data-test="product-card"
>
    <div class="aspect-video overflow-hidden bg-zinc-100 dark:bg-zinc-800">
        @if ($product->images->isNotEmpty())
            <img
                src="{{ $product->images->first()->url() }}"
                alt="{{ $product->name }}"
                class="size-full object-cover transition group-hover:scale-105"
                loading="lazy"
            >
        @else
            <div class="flex size-full items-center justify-center text-zinc-400">
                <flux:icon name="photo" class="size-10" />
            </div>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-2 p-4">
        <div class="flex items-center gap-2">
            <flux:badge size="sm">{{ $product->type->label() }}</flux:badge>

            @if ($product->category)
                <flux:text class="text-xs">{{ $product->category->name }}</flux:text>
            @endif

            @if ($product->featured)
                <flux:badge size="sm" color="amber">{{ __('Featured') }}</flux:badge>
            @endif
        </div>

        <flux:heading size="sm" class="line-clamp-1">{{ $product->name }}</flux:heading>

        @if ($product->summary)
            <flux:text class="line-clamp-2 text-sm">{{ $product->summary }}</flux:text>
        @endif

        <div class="mt-auto flex items-center justify-between pt-3">
            <span class="font-semibold">{{ $product->formattedPrice() }}</span>

            @if ($product->downloads_count > 0)
                <flux:text class="text-xs">
                    {{ trans_choice(':count download|:count downloads', $product->downloads_count, ['count' => number_format($product->downloads_count)]) }}
                </flux:text>
            @endif
        </div>
    </div>
</a>
