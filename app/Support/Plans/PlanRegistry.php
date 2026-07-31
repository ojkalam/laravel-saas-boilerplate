<?php

namespace App\Support\Plans;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class PlanRegistry
{
    /**
     * @return Collection<string, Plan>
     */
    public function all(): Collection
    {
        $plans = [];

        foreach ((array) config('plans.plans') as $key => $config) {
            if (is_string($key) && is_array($config)) {
                $plans[$key] = Plan::fromConfig($key, $config);
            }
        }

        return collect($plans);
    }

    public function find(string $key): Plan
    {
        $config = config("plans.plans.{$key}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Unknown plan [{$key}].");
        }

        return Plan::fromConfig($key, $config);
    }

    public function fromStripePrice(string $priceId): ?Plan
    {
        return $this->all()->first(
            fn (Plan $plan) => in_array($priceId, [$plan->stripeMonthly, $plan->stripeYearly], true),
        );
    }

    public function default(): Plan
    {
        return $this->find(config('plans.default'));
    }

    public function trialPlan(): Plan
    {
        return $this->find(config('plans.trial_plan'));
    }
}
