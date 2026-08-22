<?php

namespace Database\Factories;

use App\Models\Seat;
use App\Models\StudyHall;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Seat> */
class SeatFactory extends Factory
{
    protected $model = Seat::class;

    public function definition(): array
    {
        return [
            'study_hall_id' => StudyHall::factory(),
            'seat_no' => 'S-'.Str::upper(Str::random(8)),
            'seat_type' => 'regular',
            'status' => true,
        ];
    }
}
