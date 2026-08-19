<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    use HasFactory;

    protected $fillable = [
        'study_hall_id',
        'seat_no',
        'seat_type',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function studyHall()
    {
        return $this->belongsTo(StudyHall::class);
    }

    public function allocations()
    {
        return $this->hasMany(SeatAllocation::class);
    }
}
