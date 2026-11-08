<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateSuperAdmin extends Command
{
    protected $signature = 'app:admin:create {name?} {email?}';

    protected $description = 'Create the first site administrator without enabling public registration.';

    public function handle(): int
    {
        $name = trim((string) ($this->argument('name') ?? $this->ask('Administrator name')));
        $email = mb_strtolower(trim((string) ($this->argument('email') ?? $this->ask('Administrator email'))));

        $validator = Validator::make(compact('name', 'email'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = (string) $this->secret('Administrator password');
        $confirmation = (string) $this->secret('Confirm password');

        if ($password === '' || ! hash_equals($password, $confirmation)) {
            $this->error('Passwords must match and cannot be empty.');

            return self::FAILURE;
        }

        $permission = Permission::findOrCreate('manage-products', 'web');
        $role = Role::findOrCreate('super-admin', 'web');
        $role->givePermissionTo($permission);

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->assignRole($role);

        $this->info("Created Super Admin {$email}.");

        return self::SUCCESS;
    }
}
