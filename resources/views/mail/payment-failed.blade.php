<x-mail::message>
# {{ __('We could not process your payment') }}

{{ __('The latest payment for the :team team failed. Stripe will retry automatically, but please make sure your payment details are up to date.', ['team' => $team->name]) }}

{{ __('If payment keeps failing, your team will be limited to read-only access after :days days.', ['days' => $graceDays]) }}

<x-mail::button :url="route('billing.portal')">
{{ __('Update payment method') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
