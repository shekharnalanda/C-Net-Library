<?php

namespace Database\Seeders;

use App\Models\CommunicationTemplate;
use Illuminate\Database\Seeder;

class CommunicationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Fee Due Reminder',
                'slug' => 'fee-due-reminder',
                'channel' => 'whatsapp',
                'body' => 'Hello {student_name}, your C-Net Library fee due is ₹{due_amount}. Please pay before {due_date}.',
            ],
            [
                'name' => 'Membership Renewal Reminder',
                'slug' => 'membership-renewal-reminder',
                'channel' => 'whatsapp',
                'body' => 'Hello {student_name}, your C-Net Library membership expires on {expiry_date}. Please renew it to continue your seat access.',
            ],
            [
                'name' => 'Enquiry Follow-up',
                'slug' => 'enquiry-follow-up',
                'channel' => 'whatsapp',
                'body' => 'Hello {name}, thank you for your interest in C-Net Library. Our team is following up regarding your enquiry {enquiry_no}.',
            ],
        ];

        foreach ($templates as $template) {
            CommunicationTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template + ['status' => true]
            );
        }
    }
}
