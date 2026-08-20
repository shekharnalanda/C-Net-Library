<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id','book_copy_id','issued_at','due_at','returned_at','return_condition',
        'fine_amount','fine_paid','loss_charge','status','issued_by','returned_by','remarks',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'due_at' => 'date',
        'returned_at' => 'date',
        'fine_amount' => 'decimal:2',
        'fine_paid' => 'decimal:2',
        'loss_charge' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function bookCopy()
    {
        return $this->belongsTo(BookCopy::class);
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function returner()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }
}
