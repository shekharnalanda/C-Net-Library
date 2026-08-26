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
        'paid_through',
        'status',
        'remarks',
    ];

    protected $casts = [
        'allocated_from' => 'date',
        'allocated_to' => 'date',
        'monthly_charge' => 'decimal:2',
        'paid_through' => 'date',
    ];

    public function locker()
    {
        return $this->belongsTo(Locker::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function payments()
    {
        return $this->hasMany(LockerPayment::class);
    }
}
