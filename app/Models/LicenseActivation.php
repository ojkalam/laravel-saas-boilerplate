<?php

namespace App\Models;

use Database\Factories\LicenseActivationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One installed copy of a licensed product, identified by the domain
 * or host that phoned home.
 *
 * @property int $id
 * @property int $license_id
 * @property string $instance
 * @property Carbon $activated_at
 */
#[Fillable(['instance', 'activated_at'])]
class LicenseActivation extends Model
{
    /** @use HasFactory<LicenseActivationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<License, covariant $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
