<?php

namespace App\Filament\Widgets;

use App\Enums\LicenseStatus;
use App\Enums\OrderStatus;
use App\Models\Download;
use App\Models\License;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Marketplace health at a glance for staff. Every query deliberately
 * spans teams — this is the platform view, not a tenant view.
 */
class MarketplaceRevenueWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $paid = Order::acrossTeams()->where('status', OrderStatus::Paid);

        $revenueAllTime = (clone $paid)->sum('total');
        $revenueThisMonth = (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->sum('total');
        $ordersThisMonth = (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->count();

        $refunded = Order::acrossTeams()->where('status', OrderStatus::Refunded)->count();

        $activeLicenses = License::acrossTeams()->where('status', LicenseStatus::Active)->count();

        $downloadsThisMonth = Download::acrossTeams()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return [
            Stat::make('Revenue this month', $this->money($revenueThisMonth))
                ->description($ordersThisMonth.' paid '.str('order')->plural($ordersThisMonth))
                ->color('success'),

            Stat::make('Revenue all time', $this->money($revenueAllTime))
                ->description($refunded.' refunded')
                ->color($refunded > 0 ? 'warning' : 'gray'),

            Stat::make('Active licenses', number_format($activeLicenses))
                ->description(number_format($downloadsThisMonth).' downloads this month')
                ->color('primary'),
        ];
    }

    /**
     * Aggregates come back as int, float, or numeric string depending
     * on the driver and column width, so normalize before formatting.
     */
    protected function money(int|float|string $cents): string
    {
        return '$'.number_format((float) $cents / 100, 2);
    }
}
