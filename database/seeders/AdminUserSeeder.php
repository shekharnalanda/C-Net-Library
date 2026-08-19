<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@cnetlibrary.local'],
            [
                'name' => 'C-Net Library Admin',
                'password' => Hash::make('ChangeMe123!'),
                'role' => 'super_admin',
                'status' => true,
            ]
        );
    }
}
