<?php

namespace App\Enums;

enum ProductType: string
{
    case Theme = 'theme';
    case App = 'app';

    public function label(): string
    {
        return match ($this) {
            self::Theme => __('Theme'),
            self::App => __('App'),
        };
    }
}
