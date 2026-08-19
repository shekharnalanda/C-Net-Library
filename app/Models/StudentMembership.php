<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'fee_plan_id',
        'study_slot_id',
        'start_date',
        'expiry_date',
        'base_fee',
        'discount',
        'final_fee',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expiry_date' => 'date',
        'base_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'final_fee' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function feePlan()
    {
        return $this->belongsTo(FeePlan::class);
    }

    public function studySlot()
    {
        return $this->belongsTo(StudySlot::class);
    }

    public function seatAllocations()
    {
        return $this->hasMany(SeatAllocation::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'student_membership_id');
    }
}
