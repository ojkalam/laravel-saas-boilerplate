<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use App\Models\Concerns\BelongsToTeam;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Grants a team the right to use and download a product. Follows the
 * familiar model: the key never stops working, but downloads of
 * releases published after the updates window require a renewal.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $order_item_id
 * @property int $product_id
 * @property string $key
 * @property LicenseStatus $status
 * @property int $activation_limit
 * @property Carbon|null $expires_at
 * @property-read Product $product
 */
#[Fillable(['product_id', 'order_item_id', 'key', 'status', 'activation_limit', 'expires_at'])]
class License extends Model
{
    use BelongsToTeam;

    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'activation_limit' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, covariant $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<OrderItem, covariant $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return HasMany<LicenseActivation, covariant $this>
     */
    public function activations(): HasMany
    {
        return $this->hasMany(LicenseActivation::class);
    }

    /**
     * @return HasMany<Download, covariant $this>
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    public function isActive(): bool
    {
        return $this->status === LicenseStatus::Active;
    }

    public function isRevoked(): bool
    {
        return $this->status === LicenseStatus::Revoked;
    }

    /**
     * Whether the free-updates window is still open.
     */
    public function hasUpdatesAccess(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * A revoked license downloads nothing. An active one downloads any
     * release published on or before its updates window closed.
     */
    public function canDownload(ProductVersion $version): bool
    {
        if (! $this->isActive() || $version->product_id !== $this->product_id) {
            return false;
        }

        if ($this->hasUpdatesAccess()) {
            return true;
        }

        return $version->released_at !== null
            && $this->expires_at !== null
            && $version->released_at->lessThanOrEqualTo($this->expires_at);
    }

    public function remainingActivations(): int
    {
        return max(0, $this->activation_limit - $this->activations()->count());
    }

    public function hasActivationsRemaining(): bool
    {
        return $this->remainingActivations() > 0;
    }

    /**
     * Grouped for legibility, e.g. A1B2-C3D4-E5F6-G7H8.
     */
    public static function generateKey(): string
    {
        do {
            $key = collect(range(1, 4))
                ->map(fn () => Str::upper(Str::random(4)))
                ->implode('-');
        } while (static::acrossTeams()->where('key', $key)->exists());

        return $key;
    }
}
