<?php

namespace App\Filament\Resources\Licenses\Tables;

use App\Enums\LicenseStatus;
use App\Models\License;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LicensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->acrossTeams()
                ->with(['team', 'product'])
                ->withCount('activations'))
            ->columns([
                TextColumn::make('key')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable(),

                TextColumn::make('team.name')
                    ->label('Team')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (LicenseStatus $state): string => match ($state) {
                        LicenseStatus::Active => 'success',
                        LicenseStatus::Revoked => 'danger',
                    }),

                TextColumn::make('activations_count')
                    ->label('Installs')
                    ->state(fn (License $record): string => $record->activations_count.' / '.$record->activation_limit),

                TextColumn::make('expires_at')
                    ->label('Updates until')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        LicenseStatus::Active->value => 'Active',
                        LicenseStatus::Revoked->value => 'Revoked',
                    ]),

                Filter::make('updates_expired')
                    ->label('Updates expired')
                    ->query(fn ($query) => $query->whereNotNull('expires_at')->where('expires_at', '<', now())),
            ])
            ->recordActions([
                Action::make('extend')
                    ->label('Extend updates')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        TextInput::make('months')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->default(12)
                            ->required(),
                    ])
                    ->action(function (License $record, array $data): void {
                        // Extend from today when the window already
                        // lapsed, otherwise from the existing end date.
                        $base = $record->expires_at !== null && $record->expires_at->isFuture()
                            ? $record->expires_at
                            : now();

                        $record->forceFill([
                            'expires_at' => $base->copy()->addMonths((int) $data['months']),
                        ])->save();

                        activity()
                            ->causedBy(auth()->user())
                            ->performedOn($record)
                            ->withProperties(['months' => $data['months']])
                            ->log('license.extended');

                        Notification::make()->title('Updates window extended')->success()->send();
                    }),

                Action::make('seats')
                    ->label('Change seats')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        TextInput::make('activation_limit')
                            ->label('Activation limit')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->required(),
                    ])
                    ->fillForm(fn (License $record): array => ['activation_limit' => $record->activation_limit])
                    ->action(function (License $record, array $data): void {
                        $record->forceFill(['activation_limit' => (int) $data['activation_limit']])->save();

                        Notification::make()->title('Activation limit updated')->success()->send();
                    }),

                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (License $record): bool => $record->isActive())
                    ->requiresConfirmation()
                    ->modalDescription('Downloads and activations stop working immediately.')
                    ->action(function (License $record): void {
                        $record->forceFill(['status' => LicenseStatus::Revoked])->save();

                        activity()
                            ->causedBy(auth()->user())
                            ->performedOn($record)
                            ->log('license.revoked');

                        Notification::make()->title('License revoked')->success()->send();
                    }),

                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn (License $record): bool => $record->isRevoked())
                    ->requiresConfirmation()
                    ->action(function (License $record): void {
                        $record->forceFill(['status' => LicenseStatus::Active])->save();

                        activity()
                            ->causedBy(auth()->user())
                            ->performedOn($record)
                            ->log('license.restored');

                        Notification::make()->title('License restored')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
