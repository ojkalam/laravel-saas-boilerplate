<?php

namespace App\Filament\Resources\Teams\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TeamForm
{
    /**
     * Stripe customer columns are managed by Cashier/webhooks and are
     * deliberately not editable by hand.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('owner_id')
                    ->relationship('owner', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                DateTimePicker::make('trial_ends_at'),
            ]);
    }
}
