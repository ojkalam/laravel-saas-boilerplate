<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Database\Factories\UsageCounterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row per (team, metric, billing period). Incremented atomically
 * via UsageMeter — never written directly.
 *
 * @property int $id
 * @property int $team_id
 * @property string $metric
 * @property Carbon $period_start
 * @property int $value
 */
#[Fillable(['metric', 'period_start', 'value'])]
class UsageCounter extends Model
{
    use BelongsToTeam;

    /** @use HasFactory<UsageCounterFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'value' => 'integer',
        ];
    }
}
