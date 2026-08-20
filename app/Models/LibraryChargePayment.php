<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryChargePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_issue_id', 'charge_type', 'amount', 'payment_date', 'payment_mode',
        'transaction_ref', 'received_by', 'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function bookIssue()
    {
        return $this->belongsTo(BookIssue::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
