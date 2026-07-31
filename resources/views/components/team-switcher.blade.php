@php
    $user = auth()->user();
    $currentTeam = $user?->currentTeam;
    $teams = $user?->teams()->orderBy('name')->get() ?? collect();
@endphp

@if ($currentTeam)
    <flux:dropdown class="w-full" position="bottom" align="start">
        <flux:button
            variant="ghost"
            class="w-full justify-between"
            icon-trailing="chevrons-up-down"
            data-test="team-switcher"
        >
            <span class="truncate">{{ $currentTeam->name }}</span>
        </flux:button>

        <flux:menu class="w-56">
            <flux:menu.radio.group>
                <flux:text class="px-2 py-1.5 text-xs">{{ __('Teams') }}</flux:text>

                @foreach ($teams as $team)
                    <form method="POST" action="{{ route('current-team.update') }}" class="w-full">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="team_id" value="{{ $team->id }}">

                        <flux:menu.item
                            as="button"
                            type="submit"
                            class="w-full cursor-pointer"
                            :icon="$team->id === $currentTeam->id ? 'check' : null"
                        >
                            {{ $team->name }}
                        </flux:menu.item>
                    </form>
                @endforeach
            </flux:menu.radio.group>
        </flux:menu>
    </flux:dropdown>
@endif
