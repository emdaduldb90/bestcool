<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminAccessSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            'dashboard.view' => 'View dashboard',
            'products.manage' => 'Manage products',
            'categories.manage' => 'Manage categories',
            'inventory.manage' => 'Manage inventory',
            'orders.view' => 'View orders',
            'orders.manage' => 'Manage orders',
            'customers.view' => 'View customers',
            'customers.manage' => 'Manage customers',
            'content.manage' => 'Manage homepage and pages',
            'settings.manage' => 'Manage store settings',
            'reports.view' => 'View and export reports',
            'activity.view' => 'View admin activity',
            'staff.manage' => 'Manage staff roles',
            'system.view' => 'View system status',
            'tracking.view' => 'View tracking analytics',
            'tracking.manage' => 'Manage tracking and AI settings',
        ])->mapWithKeys(fn (string $name, string $slug) => [$slug => Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name])]);

        $roles = [
            'super-admin' => ['Super Admin', 'Full access to all administrative functions.', array_keys($permissions->all())],
            'manager' => ['Store Manager', 'Manage day-to-day store operations.', [
                'dashboard.view', 'products.manage', 'categories.manage', 'inventory.manage', 'orders.view', 'orders.manage',
                'customers.view', 'customers.manage', 'content.manage', 'settings.manage', 'reports.view', 'activity.view', 'system.view', 'tracking.view',
            ]],
            'orders-staff' => ['Orders Staff', 'Process orders and support customer enquiries.', [
                'dashboard.view', 'orders.view', 'orders.manage', 'customers.view',
            ]],
            'inventory-staff' => ['Inventory Staff', 'Manage stock and inventory reports.', [
                'dashboard.view', 'inventory.manage',
            ]],
            'content-editor' => ['Content Editor', 'Manage homepage and public content.', [
                'dashboard.view', 'content.manage',
            ]],
            'support' => ['Support', 'View orders and customer accounts.', [
                'dashboard.view', 'orders.view', 'customers.view',
            ]],
        ];

        foreach ($roles as $slug => [$name, $description, $allowed]) {
            $role = Role::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description]);
            $role->permissions()->sync(collect($allowed)->map(fn ($permissionSlug) => $permissions[$permissionSlug]->id)->all());
        }

        $superAdminRole = Role::query()->where('slug', 'super-admin')->first();
        User::query()->where('is_admin', true)->select('id')->chunkById(100, function ($users) use ($superAdminRole) {
            foreach ($users as $user) {
                $user->roles()->syncWithoutDetaching([$superAdminRole->id]);
            }
        });
    }
}
