<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Database\Factories\DownloadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit row written before a release file is streamed. Also the source
 * of truth for the per-license daily download cap.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $license_id
 * @property int|null $product_version_id
 * @property int|null $user_id
 * @property string|null $ip
 * @property Carbon|null $created_at
 */
#[Fillable(['license_id', 'product_version_id', 'user_id', 'ip'])]
class Download extends Model
{
    use BelongsToTeam;

    /** @use HasFactory<DownloadFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<License, covariant $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * @return BelongsTo<ProductVersion, covariant $this>
     */
    public function productVersion(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class);
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
