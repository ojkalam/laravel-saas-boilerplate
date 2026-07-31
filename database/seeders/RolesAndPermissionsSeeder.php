<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the global role set shared by all teams. Role assignments
 * are team-scoped via the model_has_roles.team_id column; the role
 * definitions themselves are global (team_id = null).
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (TeamRole::allPermissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (TeamRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web')
                ->syncPermissions($role->permissions());
        }
    }
}
