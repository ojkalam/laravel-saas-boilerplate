<x-mail::message>
# {{ __('Thanks for your purchase') }}

{{ __('Order :number is confirmed.', ['number' => $order->number]) }}

<x-mail::table>
| {{ __('Item') }} | {{ __('Price') }} |
|:-----------------|------------------:|
@foreach ($order->items as $item)
| {{ $item->product_name }} | {{ $item->unit_price === 0 ? __('Free') : '$'.number_format($item->unit_price / 100, 2) }} |
@endforeach
| **{{ __('Total') }}** | **{{ $order->formattedTotal() }}** |
</x-mail::table>

@if ($licenses->isNotEmpty())
## {{ __('Your license keys') }}

@foreach ($licenses as $license)
**{{ $license->product?->name ?? __('Product') }}**
`{{ $license->key }}`

@endforeach

{{ __('Updates are included until :date.', ['date' => $licenses->first()->expires_at?->toFormattedDateString()]) }}
@endif

<x-mail::button :url="route('purchases.index')">
{{ __('Download your files') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
