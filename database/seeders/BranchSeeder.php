<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::updateOrCreate(
            ['code' => 'CNL-MAIN'],
            [
                'name' => 'C-Net Library - Main Branch',
                'mobile' => null,
                'email' => null,
                'address' => null,
                'status' => true,
            ]
        );
    }
}
