<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use App\Models\Faq;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        CmsPage::updateOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'A better place to focus, learn and grow.',
                'menu_label' => 'Home',
                'excerpt' => 'Flexible study slots, dedicated seats, books, digital resources, attendance tracking and career updates—all in one modern study-library experience.',
                'meta_title' => 'C-Net Library | Study Hall, Library & Career Resources',
                'meta_description' => 'Join C-Net Library for flexible study slots, dedicated seating, books, digital learning resources and verified career opportunities.',
                'show_in_menu' => true,
                'sort_order' => 1,
                'status' => true,
            ]
        );

        CmsPage::updateOrCreate(
            ['slug' => 'about'],
            [
                'title' => 'About C-Net Library',
                'menu_label' => 'About',
                'excerpt' => 'A disciplined and technology-enabled study environment built for serious learners.',
                'content' => '<h2>Our purpose</h2><p>C-Net Library combines a quiet study hall with modern student services, library resources and career support. Our goal is to make focused study simple, measurable and accessible.</p><h2>What students get</h2><p>Flexible study slots, assigned seating, attendance records, books, digital resources, fee transparency and career updates through one integrated platform.</p>',
                'show_in_menu' => true,
                'sort_order' => 2,
                'status' => true,
            ]
        );

        CmsPage::updateOrCreate(
            ['slug' => 'facilities'],
            [
                'title' => 'Facilities',
                'menu_label' => 'Facilities',
                'excerpt' => 'Study infrastructure and services designed around long, focused preparation.',
                'content' => '<h2>Study Hall</h2><p>Dedicated seating with flexible plans from short-duration sessions to 24×7 access.</p><h2>Learning Resources</h2><p>Physical books, notes, question papers, ebooks, videos and member-only digital resources.</p><h2>Student Services</h2><p>Digital ID, attendance history, fee receipts, membership renewal and career updates.</p>',
                'show_in_menu' => true,
                'sort_order' => 3,
                'status' => true,
            ]
        );

        $faqs = [
            ['question' => 'Which study plans are available?', 'answer' => 'Plans can include 3, 4, 6, 8, 10 and 12-hour study access plus 24×7 and flexible options, subject to current availability.'],
            ['question' => 'Can I choose or change my seat?', 'answer' => 'Seat allocation depends on availability and your chosen study slot. Existing members can request a seat change during renewal or through the library team.'],
            ['question' => 'How do I apply?', 'answer' => 'Use the Online Admission form. After review, your membership, seat allocation and student portal account can be activated.'],
            ['question' => 'Do students get a digital ID?', 'answer' => 'Yes. Active students can access a digital ID with a QR token for verification and attendance workflows.'],
        ];

        foreach ($faqs as $index => $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                ['answer' => $faq['answer'], 'sort_order' => $index + 1, 'status' => true]
            );
        }

        Testimonial::updateOrCreate(
            ['name' => 'C-Net Student'],
            [
                'designation' => 'Competitive Exam Aspirant',
                'message' => 'The structured environment, assigned seat and flexible timing make it easier to maintain a consistent study routine.',
                'rating' => 5,
                'status' => true,
            ]
        );
    }
}
