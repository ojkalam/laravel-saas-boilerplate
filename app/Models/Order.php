<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToTeam;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A purchase made by a team. Line items copy the product name and
 * price so the record stays truthful after catalog edits.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string $number
 * @property OrderStatus $status
 * @property string $currency
 * @property int $total
 * @property string|null $stripe_checkout_session_id
 * @property Carbon|null $paid_at
 * @property Carbon|null $refunded_at
 */
#[Fillable(['user_id', 'number', 'status', 'currency', 'total', 'stripe_checkout_session_id'])]
class Order extends Model
{
    use BelongsToTeam;

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total' => 'integer',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, covariant $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasManyThrough<License, OrderItem, covariant $this>
     */
    public function licenses(): HasManyThrough
    {
        return $this->hasManyThrough(License::class, OrderItem::class, 'order_id', 'order_item_id');
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    public function isRefunded(): bool
    {
        return $this->status === OrderStatus::Refunded;
    }

    public function formattedTotal(): string
    {
        return $this->total === 0
            ? __('Free')
            : '$'.number_format($this->total / 100, 2);
    }

    /**
     * Human-facing, non-guessable order reference.
     */
    public static function generateNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (static::withoutGlobalScope('team')->where('number', $number)->exists());

        return $number;
    }
}
