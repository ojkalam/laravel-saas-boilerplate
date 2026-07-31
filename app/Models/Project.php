<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Example tenant-owned resource. Duplicate this pattern (the
 * BelongsToTeam trait + a policy + a leakage test) for every new
 * tenant model you add.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description'])]
class Project extends Model
{
    use BelongsToTeam;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;
}
