<?php

namespace Database\Seeders;

use App\Models\Admission;
use App\Models\Attendance;
use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Payment;
use App\Models\SeatAllocation;
use App\Models\Student;
use App\Models\StudentMembership;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RemoveDemoLibraryDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $studentIds = Student::whereIn('student_code', [
                'DEMO-STU-001',
                'DEMO-STU-002',
                'DEMO-STU-003',
            ])->pluck('id');

            BookIssue::whereIn('student_id', $studentIds)->delete();
            Payment::whereIn('student_id', $studentIds)->delete();
            SeatAllocation::whereIn('student_id', $studentIds)->delete();
            Attendance::whereIn('student_id', $studentIds)->delete();
            StudentMembership::whereIn('student_id', $studentIds)->delete();
            Student::whereIn('id', $studentIds)->delete();

            Admission::whereIn('application_no', [
                'DEMO-ADM-001',
                'DEMO-ADM-002',
                'DEMO-ADM-003',
            ])->delete();

            $copyIds = BookCopy::whereIn('accession_no', [
                'DEMO-BOOK-COPY-001',
                'DEMO-BOOK-COPY-002',
                'DEMO-BOOK-COPY-003',
            ])->pluck('id');

            BookIssue::whereIn('book_copy_id', $copyIds)->delete();
            BookCopy::whereIn('id', $copyIds)->delete();
            Book::where('isbn', 'DEMO-BOOK-001')->delete();
            BookCategory::where('slug', 'demo-testing')->delete();
        });

        $this->command?->info('Removed all C-Net Library DEMO students and their test records.');
    }
}
