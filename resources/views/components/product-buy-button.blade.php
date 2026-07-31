@props(['product'])

{{--
    The purchase call to action. Checkout itself is wired in the
    checkout controller; this component only decides what a visitor in
    a given state should be offered.
--}}
@if (! $product->isPurchasable())
    <flux:button variant="ghost" disabled class="w-full" data-test="buy-unavailable">
        {{ __('Not available yet') }}
    </flux:button>
@elseif (auth()->guest())
    <flux:button :href="route('login')" variant="primary" class="w-full" data-test="buy-signin">
        {{ $product->isFree() ? __('Sign in to download') : __('Sign in to buy') }}
    </flux:button>
@elseif (! Route::has('checkout.store'))
    <flux:button variant="ghost" disabled class="w-full" data-test="buy-unavailable">
        {{ __('Checkout opening soon') }}
    </flux:button>
@else
    <form method="POST" action="{{ route('checkout.store', $product) }}" class="w-full">
        @csrf
        <flux:button type="submit" variant="primary" class="w-full" data-test="buy-button">
            {{ $product->isFree() ? __('Get it free') : __('Buy for :price', ['price' => $product->formattedPrice()]) }}
        </flux:button>
    </form>
@endif
