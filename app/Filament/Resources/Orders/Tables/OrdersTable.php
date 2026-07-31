<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Actions\Marketplace\RefundOrder;
use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->acrossTeams()->with(['team', 'user', 'items']))
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('team.name')
                    ->label('Team')
                    ->searchable(),

                TextColumn::make('user.email')
                    ->label('Buyer')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('items')
                    ->label('Products')
                    ->state(fn (Order $record): string => $record->items->pluck('product_name')->implode(', ')),

                TextColumn::make('total')
                    ->state(fn (Order $record): string => $record->formattedTotal())
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Paid => 'success',
                        OrderStatus::Pending => 'gray',
                        OrderStatus::Refunded => 'warning',
                        OrderStatus::Failed => 'danger',
                    }),

                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(OrderStatus::cases())
                        ->mapWithKeys(fn (OrderStatus $status) => [$status->value => $status->label()])
                        ->all()),
            ])
            ->recordActions([
                Action::make('stripe')
                    ->label('Stripe session')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Order $record): ?string => $record->stripe_checkout_session_id
                        ? "https://dashboard.stripe.com/payments?query={$record->stripe_checkout_session_id}"
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Order $record): bool => $record->stripe_checkout_session_id !== null),

                Action::make('refund')
                    ->label('Mark refunded')
                    ->icon('heroicon-o-receipt-refund')
                    ->color('danger')
                    ->visible(fn (Order $record): bool => $record->isPaid())
                    ->requiresConfirmation()
                    ->modalDescription('This revokes every license from the order. Issue the money back in Stripe separately.')
                    ->action(function (Order $record): void {
                        app(RefundOrder::class)->handle($record);

                        Notification::make()
                            ->title('Order refunded and licenses revoked')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
