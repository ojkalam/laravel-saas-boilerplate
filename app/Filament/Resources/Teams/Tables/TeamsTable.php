<?php

namespace App\Filament\Resources\Teams\Tables;

use App\Models\Team;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

class TeamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('owner.name')
                    ->searchable(),
                IconColumn::make('personal_team')
                    ->boolean(),
                TextColumn::make('plan')
                    ->state(fn (Team $record): string => $record->plan()->name),
                TextColumn::make('subscription_status')
                    ->badge()
                    ->state(function (Team $record): string {
                        $subscription = $record->subscription('default');

                        if ($subscription !== null) {
                            return $subscription->stripe_status;
                        }

                        return $record->onGenericTrial() ? 'trialing' : 'none';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active', 'trialing' => 'success',
                        'past_due', 'unpaid', 'incomplete' => 'danger',
                        'canceled' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Members'),
                TextColumn::make('trial_ends_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('stripe')
                    ->label('Open in Stripe')
                    ->url(fn (Team $record): ?string => $record->stripe_id
                        ? "https://dashboard.stripe.com/customers/{$record->stripe_id}"
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Team $record): bool => $record->stripe_id !== null),

                Action::make('credit')
                    ->label('Apply credit')
                    ->visible(fn (Team $record): bool => $record->stripe_id !== null)
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount (USD)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('description')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Team $record, array $data): void {
                        try {
                            $record->applyBalance(
                                -(int) round(((float) $data['amount']) * 100),
                                (string) $data['description'],
                            );

                            activity()
                                ->causedBy(auth()->user())
                                ->performedOn($record)
                                ->withProperties(['amount' => $data['amount']])
                                ->log('billing.credit_applied');

                            Notification::make()
                                ->title('Credit applied')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Stripe error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
