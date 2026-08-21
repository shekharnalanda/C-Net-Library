<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        $name = env('ADMIN_NAME', 'C-Net Library Admin');

        if (! $email || ! $password) {
            $this->command?->warn('Admin user was not seeded because ADMIN_EMAIL or ADMIN_PASSWORD is not configured.');
            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'status' => true,
            ]
        );

        if (! $user->wasRecentlyCreated) {
            $user->update([
                'name' => $name,
                'role' => 'super_admin',
                'status' => true,
            ]);
        }
    }
}
