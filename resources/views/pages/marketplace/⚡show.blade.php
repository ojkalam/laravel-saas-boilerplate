<?php

use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::marketplace')]
class extends Component {
    public Product $product;

    public ?int $activeImageId = null;

    public function mount(Product $product): void
    {
        // Drafts and archived listings are not public. Staff use the
        // back-office preview instead.
        abort_unless($product->isPublished(), 404);

        $this->product = $product->load(['category', 'images', 'versions']);
        $this->activeImageId = $this->product->images->first()?->id;
    }

    public function title(): string
    {
        return $this->product->name;
    }

    public function selectImage(int $imageId): void
    {
        if ($this->product->images->contains('id', $imageId)) {
            $this->activeImageId = $imageId;
        }
    }

    #[Computed]
    public function activeImage()
    {
        return $this->product->images->firstWhere('id', $this->activeImageId)
            ?? $this->product->images->first();
    }

    #[Computed]
    public function releases(): Collection
    {
        return $this->product->versions
            ->sortByDesc('released_at')
            ->values();
    }

    #[Computed]
    public function related(): Collection
    {
        return Product::query()
            ->published()
            ->with(['category', 'images'])
            ->whereKeyNot($this->product->id)
            ->when(
                $this->product->category_id !== null,
                fn ($query) => $query->where('category_id', $this->product->category_id),
                fn ($query) => $query->where('type', $this->product->type),
            )
            ->limit(3)
            ->get();
    }
}; ?>

<div>
    <nav class="mb-6 text-sm text-zinc-500">
        <a href="{{ route('marketplace.index') }}" class="hover:underline" wire:navigate>{{ __('Marketplace') }}</a>
        <span class="mx-2">/</span>
        <a href="{{ route('marketplace.index', ['type' => $product->type->value]) }}" class="hover:underline" wire:navigate>
            {{ $product->type->label() }}
        </a>
        <span class="mx-2">/</span>
        <span class="text-zinc-700 dark:text-zinc-300">{{ $product->name }}</span>
    </nav>

    <div class="grid gap-10 lg:grid-cols-3">
        {{-- Gallery + description --}}
        <div class="lg:col-span-2">
            @if ($product->images->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <img
                        src="{{ $this->activeImage->url() }}"
                        alt="{{ $product->name }}"
                        class="aspect-video w-full object-cover"
                        data-test="active-image"
                    >
                </div>

                @if ($product->images->count() > 1)
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach ($product->images as $image)
                            <button
                                type="button"
                                wire:click="selectImage({{ $image->id }})"
                                wire:key="thumb-{{ $image->id }}"
                                class="size-20 overflow-hidden rounded-lg border-2 {{ $image->id === $this->activeImage->id ? 'border-indigo-500' : 'border-transparent' }}"
                            >
                                <img src="{{ $image->url() }}" alt="" class="size-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif

            @if (filled($product->description))
                <div class="mt-8">
                    <flux:heading size="lg">{{ __('About this :type', ['type' => strtolower($product->type->label())]) }}</flux:heading>
                    <div class="mt-3 leading-relaxed text-zinc-700 dark:text-zinc-300">
                        {!! nl2br(e($product->description)) !!}
                    </div>
                </div>
            @endif

            @if ($this->releases->isNotEmpty())
                <div class="mt-10">
                    <flux:heading size="lg">{{ __('Release history') }}</flux:heading>

                    <div class="mt-3 divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->releases as $release)
                            <div class="py-4" wire:key="release-{{ $release->id }}">
                                <div class="flex items-center gap-3">
                                    <flux:badge size="sm">v{{ $release->version }}</flux:badge>
                                    <flux:text class="text-sm">
                                        {{ $release->released_at?->toFormattedDateString() ?? '—' }}
                                        · {{ $release->formattedFileSize() }}
                                    </flux:text>
                                </div>

                                @if (filled($release->changelog))
                                    <flux:text class="mt-2 text-sm">{{ $release->changelog }}</flux:text>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Buy panel --}}
        <div>
            <div class="sticky top-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="flex items-center gap-2">
                    <flux:badge size="sm">{{ $product->type->label() }}</flux:badge>
                    @if ($product->category)
                        <flux:text class="text-sm">{{ $product->category->name }}</flux:text>
                    @endif
                </div>

                <flux:heading size="lg" class="mt-3">{{ $product->name }}</flux:heading>

                @if ($product->summary)
                    <flux:text class="mt-2">{{ $product->summary }}</flux:text>
                @endif

                <p class="mt-5 text-3xl font-bold" data-test="product-price">{{ $product->formattedPrice() }}</p>

                <div class="mt-5">
                    <x-product-buy-button :product="$product" />
                </div>

                <dl class="mt-6 space-y-2 text-sm">
                    @if ($this->releases->isNotEmpty())
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Latest version') }}</dt>
                            <dd>v{{ $this->releases->first()->version }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Updated') }}</dt>
                            <dd>{{ $this->releases->first()->released_at?->toFormattedDateString() ?? '—' }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Downloads') }}</dt>
                        <dd>{{ number_format($product->downloads_count) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Updates included') }}</dt>
                        <dd>{{ trans_choice(':count month|:count months', config('marketplace.licenses.updates_months'), ['count' => config('marketplace.licenses.updates_months')]) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    @if ($this->related->isNotEmpty())
        <div class="mt-16">
            <flux:heading size="lg">{{ __('You might also like') }}</flux:heading>

            <div class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->related as $relatedProduct)
                    <x-product-card :product="$relatedProduct" wire:key="related-{{ $relatedProduct->id }}" />
                @endforeach
            </div>
        </div>
    @endif
</div>
