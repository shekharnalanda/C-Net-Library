<?php

namespace Database\Seeders;

use App\Models\Admission;
use App\Models\Attendance;
use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\Student;
use App\Models\StudentMembership;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoLibraryDataSeeder extends Seeder
{
    private const MARKER = '[DEMO DATA — safe to remove]';

    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            StudyStructureSeeder::class,
        ]);

        DB::transaction(function (): void {
            $branch = Branch::where('code', 'CNL-MAIN')->firstOrFail();

            $plans = FeePlan::query()
                ->with('studySlot')
                ->where('branch_id', $branch->id)
                ->where('status', true)
                ->orderBy('id')
                ->take(3)
                ->get();

            if ($plans->count() < 3) {
                throw new \RuntimeException('At least three active fee plans are required for demo data.');
            }

            $category = BookCategory::updateOrCreate(
                ['slug' => 'demo-testing'],
                ['name' => '[DEMO] Testing Books', 'status' => true]
            );

            $book = Book::updateOrCreate(
                ['isbn' => 'DEMO-BOOK-001'],
                [
                    'book_category_id' => $category->id,
                    'title' => '[DEMO] Library Testing Handbook',
                    'author' => 'C-Net Library Demo',
                    'publisher' => 'C-Net Library',
                    'edition' => 'Sample Edition',
                    'publication_year' => 2026,
                    'language' => 'English',
                    'description' => self::MARKER,
                    'status' => true,
                ]
            );

            $people = [
                [
                    'code' => 'DEMO-STU-001',
                    'application' => 'DEMO-ADM-001',
                    'receipt' => 'DEMO-RCP-001',
                    'accession' => 'DEMO-BOOK-COPY-001',
                    'name' => '[DEMO] Asha Kumari',
                    'father' => '[DEMO] Rajesh Kumar',
                    'mobile' => '9000000001',
                    'email' => 'demo.student1@example.invalid',
                    'gender' => 'female',
                    'book_status' => 'returned',
                ],
                [
                    'code' => 'DEMO-STU-002',
                    'application' => 'DEMO-ADM-002',
                    'receipt' => 'DEMO-RCP-002',
                    'accession' => 'DEMO-BOOK-COPY-002',
                    'name' => '[DEMO] Ravi Kumar',
                    'father' => '[DEMO] Suresh Kumar',
                    'mobile' => '9000000002',
                    'email' => 'demo.student2@example.invalid',
                    'gender' => 'male',
                    'book_status' => 'issued',
                ],
                [
                    'code' => 'DEMO-STU-003',
                    'application' => 'DEMO-ADM-003',
                    'receipt' => 'DEMO-RCP-003',
                    'accession' => 'DEMO-BOOK-COPY-003',
                    'name' => '[DEMO] Neha Singh',
                    'father' => '[DEMO] Manoj Singh',
                    'mobile' => '9000000003',
                    'email' => 'demo.student3@example.invalid',
                    'gender' => 'female',
                    'book_status' => 'overdue',
                ],
            ];

            foreach ($people as $index => $person) {
                $plan = $plans[$index];
                $slot = $plan->studySlot;
                $startDate = today()->subDays(15);
                $expiryDate = $startDate->copy()->addDays(max(1, (int) $plan->validity_days));

                Admission::updateOrCreate(
                    ['application_no' => $person['application']],
                    [
                        'branch_id' => $branch->id,
                        'name' => $person['name'],
                        'father_name' => $person['father'],
                        'dob' => now()->subYears(20 + $index)->subMonths($index + 1)->toDateString(),
                        'gender' => $person['gender'],
                        'mobile' => $person['mobile'],
                        'email' => $person['email'],
                        'address' => self::MARKER,
                        'study_slot_id' => $slot?->id,
                        'fee_plan_id' => $plan->id,
                        'status' => 'converted',
                        'remarks' => self::MARKER,
                    ]
                );

                $student = Student::updateOrCreate(
                    ['student_code' => $person['code']],
                    [
                        'branch_id' => $branch->id,
                        'name' => $person['name'],
                        'father_name' => $person['father'],
                        'dob' => now()->subYears(20 + $index)->subMonths($index + 1)->toDateString(),
                        'gender' => $person['gender'],
                        'mobile' => $person['mobile'],
                        'email' => $person['email'],
                        'address' => self::MARKER,
                        'joining_date' => $startDate->toDateString(),
                        'status' => 'active',
                    ]
                );

                $membership = StudentMembership::updateOrCreate(
                    ['student_id' => $student->id, 'status' => 'active'],
                    [
                        'fee_plan_id' => $plan->id,
                        'study_slot_id' => $slot?->id,
                        'start_date' => $startDate->toDateString(),
                        'expiry_date' => $expiryDate->toDateString(),
                        'base_fee' => $plan->monthly_fee,
                        'discount' => 0,
                        'final_fee' => $plan->monthly_fee,
                    ]
                );

                $allocation = SeatAllocation::where('student_id', $student->id)
                    ->whereIn('status', ['reserved', 'active'])
                    ->first();

                if (! $allocation) {
                    $seat = Seat::query()
                        ->whereHas('studyHall', fn ($query) => $query->where('branch_id', $branch->id))
                        ->where('status', true)
                        ->whereDoesntHave('allocations', function ($query): void {
                            $query->whereIn('status', ['reserved', 'active'])
                                ->whereDate('allocated_from', '<=', today())
                                ->where(function ($dates): void {
                                    $dates->whereNull('allocated_to')
                                        ->orWhereDate('allocated_to', '>=', today());
                                });
                        })
                        ->orderBy('id')
                        ->firstOrFail();

                    SeatAllocation::create([
                        'student_id' => $student->id,
                        'student_membership_id' => $membership->id,
                        'seat_id' => $seat->id,
                        'study_slot_id' => $slot?->id,
                        'allocated_from' => $startDate->toDateString(),
                        'allocated_to' => $expiryDate->toDateString(),
                        'start_time' => $slot?->start_time,
                        'end_time' => $slot?->end_time,
                        'status' => 'active',
                        'remarks' => self::MARKER,
                    ]);
                }

                Payment::updateOrCreate(
                    ['receipt_no' => $person['receipt']],
                    [
                        'student_id' => $student->id,
                        'student_membership_id' => $membership->id,
                        'amount' => $plan->monthly_fee,
                        'receipt_previous_paid' => 0,
                        'receipt_balance_due' => 0,
                        'receipt_membership_fee' => $plan->monthly_fee,
                        'discount' => 0,
                        'late_fee' => 0,
                        'payment_date' => today()->subDays(14)->toDateString(),
                        'payment_mode' => 'cash',
                        'transaction_ref' => null,
                        'payment_status' => 'paid',
                        'received_by' => null,
                        'remarks' => self::MARKER,
                    ]
                );

                foreach (range(0, 2) as $dayOffset) {
                    $date = today()->subDays($dayOffset);

                    Attendance::updateOrCreate(
                        ['student_id' => $student->id, 'attendance_date' => $date->toDateString()],
                        [
                            'branch_id' => $branch->id,
                            'check_in_at' => $date->copy()->setTime(8 + $index, 0),
                            'check_out_at' => $date->copy()->setTime(11 + $index, 0),
                            'study_minutes' => 180,
                            'entry_method' => 'manual',
                            'marked_by' => null,
                            'remarks' => self::MARKER,
                        ]
                    );
                }

                $copy = BookCopy::updateOrCreate(
                    ['accession_no' => $person['accession']],
                    [
                        'book_id' => $book->id,
                        'branch_id' => $branch->id,
                        'barcode' => $person['accession'],
                        'rack_no' => 'DEMO-RACK',
                        'condition' => 'good',
                        'status' => $person['book_status'] === 'returned' ? 'available' : 'issued',
                    ]
                );

                $isReturned = $person['book_status'] === 'returned';

                BookIssue::updateOrCreate(
                    ['student_id' => $student->id, 'book_copy_id' => $copy->id],
                    [
                        'issued_at' => today()->subDays(10)->toDateString(),
                        'due_at' => ($person['book_status'] === 'overdue'
                            ? today()->subDays(2)
                            : today()->addDays(4))->toDateString(),
                        'returned_at' => $isReturned ? today()->subDay()->toDateString() : null,
                        'return_condition' => $isReturned ? 'good' : null,
                        'fine_amount' => 0,
                        'fine_paid' => 0,
                        'loss_charge' => 0,
                        'status' => $person['book_status'],
                        'issued_by' => null,
                        'returned_by' => null,
                        'remarks' => self::MARKER,
                    ]
                );
            }
        });

        $this->command?->info('Created or refreshed 3 clearly labelled DEMO students and their test records.');
    }
}
