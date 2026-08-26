<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LockerAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'locker_id',
        'student_id',
        'allocated_from',
        'allocated_to',
        'monthly_charge',
        'status',
        'remarks',
    ];

    protected $casts = [
        'allocated_from' => 'date',
        'allocated_to' => 'date',
        'monthly_charge' => 'decimal:2',
    ];

    public function locker()
    {
        return $this->belongsTo(Locker::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
