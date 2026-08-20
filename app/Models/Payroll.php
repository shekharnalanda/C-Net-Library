<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id', 'month', 'year', 'basic_salary', 'allowances', 'deductions',
        'net_salary', 'status', 'paid_on', 'payment_mode', 'transaction_ref',
        'processed_by', 'remarks',
    ];

    protected $casts = [
        'paid_on' => 'date',
        'basic_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function expense()
    {
        return $this->hasOne(Expense::class);
    }
}
