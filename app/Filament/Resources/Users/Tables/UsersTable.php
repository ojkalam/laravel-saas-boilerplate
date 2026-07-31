<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Support\Impersonation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('currentTeam.name')
                    ->searchable(),
                IconColumn::make('is_staff')
                    ->boolean(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('impersonate')
                    ->label('Impersonate')
                    ->icon('heroicon-o-identification')
                    ->visible(fn (User $record): bool => ! $record->is_staff
                        && $record->id !== auth()->id())
                    ->requiresConfirmation()
                    ->modalDescription('The impersonation is time-boxed and written to the audit log.')
                    ->action(function (User $record) {
                        /** @var User $staff */
                        $staff = auth()->user();

                        app(Impersonation::class)->start($staff, $record);

                        return redirect()->route('dashboard');
                    }),
            ]);
    }
}
