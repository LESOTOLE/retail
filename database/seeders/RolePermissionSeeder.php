<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder RBAC Spatie Permission sesuai matriks hak akses PRD bagian 2.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Daftar permission per role.
     *
     * @var array<string, array<int, string>>
     */
    protected array $matrix = [
        UserRole::Admin->value => [
            'product.view', 'product.manage',
            'vehicle.view', 'vehicle.manage',
            'inventory.view', 'inventory.adjust',
            'order.view-any', 'order.create', 'order.fulfill',
            'ai.chat', 'ai.audit',
            'report.view',
        ],
        UserRole::Staff->value => [
            'product.view',
            'vehicle.view',
            'inventory.view', 'inventory.adjust',
            'order.view-any', 'order.create', 'order.fulfill',
            'ai.chat',
        ],
        UserRole::Customer->value => [
            'product.view',
            'vehicle.view',
            'order.create',
            'ai.chat',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissions = collect($this->matrix)->flatten()->unique();

        foreach ($allPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach ($this->matrix as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
