<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            StudyStructureSeeder::class,
            AdminUserSeeder::class,
            CommunicationTemplateSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
