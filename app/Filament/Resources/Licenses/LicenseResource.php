<?php

namespace App\Filament\Resources\Licenses;

use App\Filament\Resources\Licenses\Pages\ListLicenses;
use App\Filament\Resources\Licenses\Tables\LicensesTable;
use App\Models\License;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Licenses are minted by fulfillment. Staff manage them through the
 * row actions (revoke, restore, extend, change seats) rather than by
 * editing fields directly.
 */
class LicenseResource extends Resource
{
    protected static ?string $model = License::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return LicensesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLicenses::route('/'),
        ];
    }
}
