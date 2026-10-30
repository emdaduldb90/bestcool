<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class RevokeAdminAccess extends Command
{
    protected $signature = 'admin:revoke {email : Email address of the administrator} {--force : Skip the confirmation prompt}';

    protected $description = 'Revoke administrator access without removing the last active super admin';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if (! $user || (! $user->isAdmin() && ! $user->hasRole('super-admin'))) {
            $this->error('No super administrator exists for that email.');

            return self::FAILURE;
        }

        $activeAdmins = User::query()->where('is_active', true)->where(function ($query) {
            $query->where('is_admin', true)->orWhereHas('roles', fn ($roles) => $roles->where('slug', 'super-admin'));
        })->count();
        if ($user->is_active && $activeAdmins <= 1) {
            $this->error('The last active super administrator cannot be demoted.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Revoke super admin access for {$user->email}?", false)) {
            $this->comment('No changes made.');

            return self::SUCCESS;
        }

        $superAdminRole = Role::query()->where('slug', 'super-admin')->first();
        if ($superAdminRole) {
            $user->roles()->detach($superAdminRole->id);
        }
        $user->forceFill(['is_admin' => false])->save();
        $this->info('Super admin access revoked.');

        return self::SUCCESS;
    }
}
