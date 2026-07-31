<?php

use App\Actions\Teams\ChangeMemberRole;
use App\Actions\Teams\DeleteTeam;
use App\Actions\Teams\InviteTeamMember;
use App\Actions\Teams\LeaveTeam;
use App\Actions\Teams\RemoveTeamMember;
use App\Actions\Teams\UpdateTeamName;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\CurrentTeam;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Team settings')] class extends Component {
    public string $name = '';

    public string $inviteEmail = '';

    public string $inviteRole = 'member';

    public string $deleteConfirmation = '';

    public function mount(): void
    {
        abort_if($this->team() === null, 403);

        Gate::authorize('view', $this->team());

        $this->name = $this->team()->name;
    }

    #[Computed]
    public function team(): ?Team
    {
        return app(CurrentTeam::class)->model();
    }

    #[Computed]
    public function members()
    {
        return $this->team()->members()->orderBy('name')->get();
    }

    #[Computed]
    public function pendingInvitations()
    {
        return $this->team()->invitations()->orderBy('created_at')->get();
    }

    #[Computed]
    public function canManageMembers(): bool
    {
        return Gate::allows('manageMembers', $this->team());
    }

    public function updateName(): void
    {
        Gate::authorize('update', $this->team());

        $validated = $this->validate(['name' => ['required', 'string', 'max:255']]);

        app(UpdateTeamName::class)->handle($this->team(), $validated['name']);

        Flux::toast(variant: 'success', text: __('Team name updated.'));
    }

    public function inviteMember(): void
    {
        Gate::authorize('manageMembers', $this->team());

        $validated = $this->validate([
            'inviteEmail' => ['required', 'email', 'max:255'],
            'inviteRole' => ['required', 'in:admin,member,billing'],
        ]);

        app(InviteTeamMember::class)->handle(
            $this->team(),
            $validated['inviteEmail'],
            $validated['inviteRole'],
        );

        $this->reset('inviteEmail');
        $this->inviteRole = 'member';
        unset($this->pendingInvitations);

        Flux::toast(variant: 'success', text: __('Invitation sent.'));
    }

    public function cancelInvitation(int $invitationId): void
    {
        Gate::authorize('manageMembers', $this->team());

        $this->team()->invitations()->whereKey($invitationId)->delete();

        unset($this->pendingInvitations);
    }

    public function changeRole(int $memberId, string $role): void
    {
        Gate::authorize('manageMembers', $this->team());

        app(ChangeMemberRole::class)->handle(
            $this->team(),
            User::findOrFail($memberId),
            $role,
        );

        unset($this->members);

        Flux::toast(variant: 'success', text: __('Role updated.'));
    }

    public function removeMember(int $memberId): void
    {
        Gate::authorize('manageMembers', $this->team());

        app(RemoveTeamMember::class)->handle($this->team(), User::findOrFail($memberId));

        unset($this->members);

        Flux::toast(variant: 'success', text: __('Member removed.'));
    }

    public function leaveTeam(): void
    {
        app(LeaveTeam::class)->handle($this->team(), Auth::user());

        $this->redirectIntended(default: route('dashboard', absolute: false));
    }

    public function deleteTeam(): void
    {
        Gate::authorize('delete', $this->team());

        if ($this->deleteConfirmation !== $this->team()->name) {
            $this->addError('deleteConfirmation', __('Type the team name exactly to confirm.'));

            return;
        }

        app(DeleteTeam::class)->handle($this->team());

        $this->redirectIntended(default: route('dashboard', absolute: false));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Team settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Team')" :subheading="__('Manage your team, members, and invitations')">
        <div class="my-6 space-y-10">
            {{-- Team name --}}
            @can('update', $this->team)
                <form wire:submit="updateName" class="space-y-4">
                    <flux:input wire:model="name" :label="__('Team name')" required data-test="team-name-input" />
                    <flux:button variant="primary" type="submit" data-test="update-team-name-button">
                        {{ __('Save') }}
                    </flux:button>
                </form>
            @endcan

            {{-- Members --}}
            <div>
                <flux:heading size="sm">{{ __('Members') }}</flux:heading>

                <div class="mt-3 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($this->members as $member)
                        <div class="flex items-center justify-between gap-3 py-3" wire:key="member-{{ $member->id }}">
                            <div class="min-w-0">
                                <flux:text class="truncate font-medium">{{ $member->name }}</flux:text>
                                <flux:text class="truncate text-sm">{{ $member->email }}</flux:text>
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($member->id === $this->team->owner_id)
                                    <flux:badge size="sm">{{ __('owner') }}</flux:badge>
                                @elseif ($this->canManageMembers)
                                    <flux:select
                                        wire:change="changeRole({{ $member->id }}, $event.target.value)"
                                        size="sm"
                                        data-test="member-role-select-{{ $member->id }}"
                                    >
                                        @foreach (['admin', 'member', 'billing'] as $role)
                                            <option value="{{ $role }}" @selected($member->pivot->role === $role)>
                                                {{ __($role) }}
                                            </option>
                                        @endforeach
                                    </flux:select>

                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="removeMember({{ $member->id }})"
                                        wire:confirm="{{ __('Remove this member from the team?') }}"
                                        data-test="remove-member-{{ $member->id }}"
                                    >
                                        {{ __('Remove') }}
                                    </flux:button>
                                @else
                                    <flux:badge size="sm">{{ __($member->pivot->role) }}</flux:badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Invitations --}}
            @if ($this->canManageMembers)
                <div>
                    <flux:heading size="sm">{{ __('Invite a member') }}</flux:heading>

                    <form wire:submit="inviteMember" class="mt-3 flex flex-wrap items-end gap-3">
                        <div class="min-w-56 flex-1">
                            <flux:input wire:model="inviteEmail" type="email" :label="__('Email')" required data-test="invite-email-input" />
                        </div>
                        <flux:select wire:model="inviteRole" :label="__('Role')" data-test="invite-role-select">
                            @foreach (['admin', 'member', 'billing'] as $role)
                                <option value="{{ $role }}">{{ __($role) }}</option>
                            @endforeach
                        </flux:select>
                        <flux:button variant="primary" type="submit" data-test="send-invitation-button">
                            {{ __('Invite') }}
                        </flux:button>
                    </form>

                    @if ($this->pendingInvitations->isNotEmpty())
                        <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($this->pendingInvitations as $invitation)
                                <div class="flex items-center justify-between py-2" wire:key="invitation-{{ $invitation->id }}">
                                    <flux:text class="text-sm">
                                        {{ $invitation->email }} — {{ __($invitation->role) }}
                                    </flux:text>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="cancelInvitation({{ $invitation->id }})"
                                        data-test="cancel-invitation-{{ $invitation->id }}"
                                    >
                                        {{ __('Cancel') }}
                                    </flux:button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            {{-- Danger zone --}}
            <div class="rounded-xl border border-red-200 p-5 dark:border-red-900">
                <flux:heading size="sm" class="text-red-600 dark:text-red-400">{{ __('Danger zone') }}</flux:heading>

                <div class="mt-4 space-y-6">
                    @if ($this->team->owner_id !== auth()->id())
                        <div>
                            <flux:button
                                variant="danger"
                                wire:click="leaveTeam"
                                wire:confirm="{{ __('Leave this team?') }}"
                                data-test="leave-team-button"
                            >
                                {{ __('Leave team') }}
                            </flux:button>
                        </div>
                    @endif

                    @can('delete', $this->team)
                        <form wire:submit="deleteTeam" class="space-y-3">
                            <flux:text class="text-sm">
                                {{ __('Deleting a team is permanent. Type :name to confirm.', ['name' => $this->team->name]) }}
                            </flux:text>
                            <flux:input wire:model="deleteConfirmation" data-test="delete-confirmation-input" />
                            <flux:button variant="danger" type="submit" data-test="delete-team-button">
                                {{ __('Delete team') }}
                            </flux:button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
