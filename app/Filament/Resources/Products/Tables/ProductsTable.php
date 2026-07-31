<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (ProductType $state): string => $state->label()),

                TextColumn::make('category.name')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('price')
                    ->label('Price')
                    ->state(fn (Product $record): string => $record->formattedPrice())
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ProductStatus $state): string => match ($state) {
                        ProductStatus::Published => 'success',
                        ProductStatus::Draft => 'gray',
                        ProductStatus::Archived => 'warning',
                    }),

                TextColumn::make('versions_count')
                    ->counts('versions')
                    ->label('Releases'),

                IconColumn::make('featured')
                    ->boolean(),

                TextColumn::make('downloads_count')
                    ->label('Downloads')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        ProductStatus::Draft->value => 'Draft',
                        ProductStatus::Published->value => 'Published',
                        ProductStatus::Archived->value => 'Archived',
                    ]),

                SelectFilter::make('type')
                    ->options([
                        ProductType::Theme->value => 'Theme',
                        ProductType::App->value => 'App',
                    ]),

                SelectFilter::make('category')
                    ->relationship('category', 'name'),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Product $record): bool => ! $record->isPublished())
                    ->requiresConfirmation()
                    ->action(function (Product $record): void {
                        // Publishing with nothing to download would sell
                        // an empty box.
                        if (! $record->versions()->exists()) {
                            Notification::make()
                                ->title('Add a release first')
                                ->body('A product needs at least one version before it can be published.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['status' => ProductStatus::Published]);

                        Notification::make()->title('Product published')->success()->send();
                    }),

                Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (Product $record): bool => $record->isPublished())
                    ->requiresConfirmation()
                    ->modalDescription('Existing buyers keep their licenses and downloads.')
                    ->action(function (Product $record): void {
                        $record->update(['status' => ProductStatus::Draft]);

                        Notification::make()->title('Product unpublished')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
