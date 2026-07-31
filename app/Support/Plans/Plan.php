<?php

namespace App\Support\Plans;

/**
 * Immutable view of one entry in config/plans.php.
 */
class Plan
{
    /**
     * @param  array<string, int|null>  $limits
     * @param  array<string, bool>  $features
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly int $monthlyPrice,
        public readonly int $yearlyPrice,
        public readonly ?string $stripeMonthly,
        public readonly ?string $stripeYearly,
        public readonly bool $perSeat,
        public readonly array $limits,
        public readonly array $features,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            key: $key,
            name: $config['name'],
            monthlyPrice: $config['monthly_price'] ?? 0,
            yearlyPrice: $config['yearly_price'] ?? 0,
            stripeMonthly: $config['stripe_monthly'] ?? null,
            stripeYearly: $config['stripe_yearly'] ?? null,
            perSeat: $config['per_seat'] ?? false,
            limits: $config['limits'] ?? [],
            features: $config['features'] ?? [],
        );
    }

    public function allows(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }

    /**
     * A null limit means unlimited; a missing metric means zero.
     */
    public function limit(string $metric): ?int
    {
        if (! array_key_exists($metric, $this->limits)) {
            return 0;
        }

        return $this->limits[$metric];
    }

    public function isUnlimited(string $metric): bool
    {
        return array_key_exists($metric, $this->limits) && $this->limits[$metric] === null;
    }

    public function isFree(): bool
    {
        return $this->stripeMonthly === null && $this->stripeYearly === null;
    }

    public function stripePriceFor(string $interval): ?string
    {
        return match ($interval) {
            'monthly' => $this->stripeMonthly,
            'yearly' => $this->stripeYearly,
            default => null,
        };
    }
}
