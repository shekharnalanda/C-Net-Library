<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'payroll_id',
        'expense_date',
        'category',
        'payee',
        'amount',
        'payment_mode',
        'transaction_ref',
        'description',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function adjustments()
    {
        return $this->hasMany(ExpenseAdjustment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
