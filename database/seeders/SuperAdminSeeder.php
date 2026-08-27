<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $credentials = config('school.super_admin');

        if (! is_array($credentials) || blank($credentials['email'] ?? null) || blank($credentials['password'] ?? null)) {
            throw new InvalidArgumentException('SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD must be configured.');
        }

        if (app()->isProduction() && $credentials['password'] === 'ChangeMe!2026') {
            throw new InvalidArgumentException('Set a secure SUPER_ADMIN_PASSWORD before seeding production.');
        }

        $superAdmin = User::withTrashed()->updateOrCreate(
            ['email' => $credentials['email']],
            [
                'name' => $credentials['name'],
                'phone' => $credentials['phone'],
                'password' => Hash::make($credentials['password']),
                'status' => 'active',
                'locale' => 'en',
                'must_change_password' => true,
                'email_verified_at' => now(),
                'deleted_at' => null,
            ],
        );

        $superAdmin->syncRoles(RoleName::SuperAdmin->value);
    }
}
