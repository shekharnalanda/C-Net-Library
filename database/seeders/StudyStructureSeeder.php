<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Seat;
use App\Models\StudyHall;
use App\Models\StudySlot;
use Illuminate\Database\Seeder;

class StudyStructureSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::where('code', 'CNL-MAIN')->firstOrFail();

        $hallA = StudyHall::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Hall A'],
            ['floor' => 'Ground Floor', 'total_seats' => 20, 'status' => true]
        );

        $hallB = StudyHall::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Hall B'],
            ['floor' => 'First Floor', 'total_seats' => 20, 'status' => true]
        );

        foreach ([$hallA, $hallB] as $hall) {
            $prefix = $hall->name === 'Hall A' ? 'A' : 'B';

            for ($i = 1; $i <= 20; $i++) {
                Seat::updateOrCreate(
                    ['study_hall_id' => $hall->id, 'seat_no' => sprintf('%s-%02d', $prefix, $i)],
                    ['seat_type' => 'regular', 'status' => true]
                );
            }
        }

        $slots = [
            ['name' => '3 Hours', 'duration_hours' => 3, 'start_time' => '06:00:00', 'end_time' => '09:00:00', 'is_24x7' => false, 'is_flexible' => true],
            ['name' => '4 Hours', 'duration_hours' => 4, 'start_time' => '06:00:00', 'end_time' => '10:00:00', 'is_24x7' => false, 'is_flexible' => true],
            ['name' => '6 Hours', 'duration_hours' => 6, 'start_time' => '06:00:00', 'end_time' => '12:00:00', 'is_24x7' => false, 'is_flexible' => true],
            ['name' => '8 Hours', 'duration_hours' => 8, 'start_time' => '06:00:00', 'end_time' => '14:00:00', 'is_24x7' => false, 'is_flexible' => true],
            ['name' => '10 Hours', 'duration_hours' => 10, 'start_time' => '06:00:00', 'end_time' => '16:00:00', 'is_24x7' => false, 'is_flexible' => true],
            ['name' => '12 Hours', 'duration_hours' => 12, 'start_time' => '06:00:00', 'end_time' => '18:00:00', 'is_24x7' => false, 'is_flexible' => true],
            ['name' => '24x7', 'duration_hours' => 24, 'start_time' => null, 'end_time' => null, 'is_24x7' => true, 'is_flexible' => false],
        ];

        foreach ($slots as $slotData) {
            $slot = StudySlot::updateOrCreate(
                ['branch_id' => $branch->id, 'name' => $slotData['name']],
                array_merge($slotData, ['status' => true])
            );

            $fees = [
                '3 Hours' => 700,
                '4 Hours' => 800,
                '6 Hours' => 1000,
                '8 Hours' => 1200,
                '10 Hours' => 1400,
                '12 Hours' => 1600,
                '24x7' => 2200,
            ];

            FeePlan::updateOrCreate(
                ['branch_id' => $branch->id, 'study_slot_id' => $slot->id, 'name' => $slot->name . ' Monthly'],
                [
                    'monthly_fee' => $fees[$slot->name],
                    'quarterly_fee' => null,
                    'half_yearly_fee' => null,
                    'yearly_fee' => null,
                    'admission_fee' => 0,
                    'registration_fee' => 0,
                    'security_deposit' => 0,
                    'late_fee' => 0,
                    'validity_days' => 30,
                    'status' => true,
                ]
            );
        }
    }
}
