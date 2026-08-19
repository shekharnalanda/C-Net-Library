<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeatAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'student_membership_id',
        'seat_id',
        'study_slot_id',
        'allocated_from',
        'allocated_to',
        'start_time',
        'end_time',
        'status',
        'remarks',
    ];

    protected $casts = [
        'allocated_from' => 'date',
        'allocated_to' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function membership()
    {
        return $this->belongsTo(StudentMembership::class, 'student_membership_id');
    }

    public function seat()
    {
        return $this->belongsTo(Seat::class);
    }

    public function studySlot()
    {
        return $this->belongsTo(StudySlot::class);
    }
}
