<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'student_membership_id',
        'receipt_no',
        'amount',
        'discount',
        'late_fee',
        'payment_date',
        'payment_mode',
        'transaction_ref',
        'payment_status',
        'received_by',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function membership()
    {
        return $this->belongsTo(StudentMembership::class, 'student_membership_id');
    }
}
