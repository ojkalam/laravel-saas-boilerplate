<x-mail::message>
# {{ __('You have been invited to join :team', ['team' => $invitation->team->name]) }}

{{ __('You have been invited to join the :team team as :role.', ['team' => $invitation->team->name, 'role' => $invitation->role]) }}

{{ __('If you do not have an account yet, register with this email address first, then open the link below.') }}

<x-mail::button :url="$acceptUrl">
{{ __('Accept Invitation') }}
</x-mail::button>

{{ __('This invitation expires :date.', ['date' => $invitation->expires_at->toFormattedDateString()]) }}

{{ __('If you did not expect this invitation, you can ignore this email.') }}

{{ config('app.name') }}
</x-mail::message>
