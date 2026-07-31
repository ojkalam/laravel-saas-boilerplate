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

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (TeamRole::cases() as $role) {
            // Sync by model, not by name — name resolution depends on the
            // registrar cache, which can be stale on a fresh database.
            $permissions = Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $role->permissions())
                ->get();

            Role::findOrCreate($role->value, 'web')
                ->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
