<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;

class PromoteUserToAdmin extends Command
{
    protected $signature = 'admin:promote {email : Email address of the existing account} {--force : Skip the confirmation prompt}';

    protected $description = 'Grant admin panel access to an existing account';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No account exists for that email. Register it first, then run this command again.');

            return self::FAILURE;
        }

        if ($user->isAdmin()) {
            $this->info('This account already has admin access.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Grant admin panel access to {$user->email}?", false)) {
            $this->comment('No changes made.');

            return self::SUCCESS;
        }

        $user->forceFill(['is_admin' => true])->save();
        if ($role = Role::query()->where('slug', 'super-admin')->first()) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
        $this->info('Admin access granted.');

        return self::SUCCESS;
    }
}
