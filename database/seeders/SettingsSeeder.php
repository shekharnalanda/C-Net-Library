<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['group' => 'institute', 'key' => 'institute_name', 'value' => 'C-Net Library', 'type' => 'string', 'is_public' => true],
            ['group' => 'institute', 'key' => 'support_phone', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'institute', 'key' => 'support_email', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'codes', 'key' => 'student_code_prefix', 'value' => 'CNL-STU', 'type' => 'string', 'is_public' => false],
            ['group' => 'codes', 'key' => 'receipt_prefix', 'value' => 'CNL', 'type' => 'string', 'is_public' => false],
            ['group' => 'membership', 'key' => 'membership_grace_days', 'value' => '0', 'type' => 'integer', 'is_public' => false],
            ['group' => 'library', 'key' => 'book_issue_days', 'value' => '14', 'type' => 'integer', 'is_public' => false],
            ['group' => 'library', 'key' => 'book_fine_per_day', 'value' => '5', 'type' => 'decimal', 'is_public' => false],
            ['group' => 'finance', 'key' => 'payment_modes', 'value' => json_encode(['cash','upi','card','bank_transfer','other']), 'type' => 'json', 'is_public' => false],
            ['group' => 'attendance', 'key' => 'qr_cooldown_seconds', 'value' => '30', 'type' => 'integer', 'is_public' => false],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['branch_id' => null, 'key' => $setting['key']],
                $setting + ['branch_id' => null]
            );
        }
    }
}
