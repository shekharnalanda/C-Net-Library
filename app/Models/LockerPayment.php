<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LockerPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'locker_allocation_id',
        'student_id',
        'receipt_no',
        'billing_months',
        'monthly_charge',
        'amount',
        'period_from',
        'period_to',
        'payment_date',
        'payment_mode',
        'transaction_ref',
        'received_by',
        'status',
        'remarks',
    ];

    protected $casts = [
        'monthly_charge' => 'decimal:2',
        'amount' => 'decimal:2',
        'period_from' => 'date',
        'period_to' => 'date',
        'payment_date' => 'date',
    ];

    public function allocation()
    {
        return $this->belongsTo(LockerAllocation::class, 'locker_allocation_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
