<?php

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts::marketplace')]
#[Title('Marketplace')]
class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: 'featured')]
    public string $sort = 'featured';

    /**
     * Any filter change must reset paging, otherwise a narrowed result
     * set lands the visitor on an empty page.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'category', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'type', 'category', 'sort');
        $this->resetPage();
    }

    #[Computed]
    public function categories(): Collection
    {
        return ProductCategory::query()->orderBy('name')->get();
    }

    #[Computed]
    public function products(): LengthAwarePaginator
    {
        return Product::query()
            ->published()
            ->with(['category', 'images'])
            ->when($this->search !== '', function ($query): void {
                $term = '%'.str_replace('%', '\%', $this->search).'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'ilike', $term)
                        ->orWhere('summary', 'ilike', $term)
                        ->orWhere('description', 'ilike', $term);
                });
            })
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when($this->category !== '', fn ($query) => $query->whereHas(
                'category',
                fn ($query) => $query->where('slug', $this->category),
            ))
            ->when($this->sort === 'featured', fn ($query) => $query
                ->orderByDesc('featured')
                ->orderByDesc('created_at'))
            ->when($this->sort === 'newest', fn ($query) => $query->orderByDesc('created_at'))
            ->when($this->sort === 'price_asc', fn ($query) => $query->orderBy('price'))
            ->when($this->sort === 'price_desc', fn ($query) => $query->orderByDesc('price'))
            ->when($this->sort === 'popular', fn ($query) => $query->orderByDesc('downloads_count'))
            ->paginate(12);
    }

    #[Computed]
    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->type !== '' || $this->category !== '';
    }
}; ?>

<div>
    <div class="mb-8">
        <flux:heading size="xl">{{ __('Themes & apps for your business') }}</flux:heading>
        <flux:subheading class="mt-2">
            {{ __('Buy once, download forever, get a year of updates.') }}
        </flux:subheading>
    </div>

    {{-- Filters --}}
    <div class="mb-8 flex flex-wrap items-end gap-3">
        <div class="min-w-64 flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                type="search"
                icon="magnifying-glass"
                :placeholder="__('Search themes and apps')"
                data-test="search-input"
            />
        </div>

        <flux:select wire:model.live="type" :label="__('Type')" class="max-w-40" data-test="type-filter">
            <option value="">{{ __('All types') }}</option>
            @foreach (ProductType::cases() as $productType)
                <option value="{{ $productType->value }}">{{ $productType->label() }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="category" :label="__('Category')" class="max-w-48" data-test="category-filter">
            <option value="">{{ __('All categories') }}</option>
            @foreach ($this->categories as $categoryOption)
                <option value="{{ $categoryOption->slug }}">{{ $categoryOption->name }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="sort" :label="__('Sort')" class="max-w-44" data-test="sort-filter">
            <option value="featured">{{ __('Featured') }}</option>
            <option value="newest">{{ __('Newest') }}</option>
            <option value="popular">{{ __('Most downloaded') }}</option>
            <option value="price_asc">{{ __('Price: low to high') }}</option>
            <option value="price_desc">{{ __('Price: high to low') }}</option>
        </flux:select>

        @if ($this->hasFilters)
            <flux:button wire:click="clearFilters" variant="ghost" data-test="clear-filters">
                {{ __('Clear') }}
            </flux:button>
        @endif
    </div>

    {{-- Results --}}
    @if ($this->products->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 py-16 text-center dark:border-zinc-700" data-test="empty-state">
            <flux:heading size="sm">{{ __('Nothing matches those filters') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Try a different search or clear the filters.') }}</flux:text>
        </div>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->products as $product)
                <x-product-card :product="$product" wire:key="product-{{ $product->id }}" />
            @endforeach
        </div>

        <div class="mt-10">
            {{ $this->products->links() }}
        </div>
    @endif
</div>
