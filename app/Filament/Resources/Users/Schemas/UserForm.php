<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    /**
     * Passwords and 2FA secrets are never editable from the
     * back-office; account recovery goes through the normal reset flow.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                Select::make('current_team_id')
                    ->relationship('currentTeam', 'name'),
                Toggle::make('is_staff'),
            ]);
    }
}
