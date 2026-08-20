<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_copy_id', 'student_id', 'status', 'reserved_at', 'expires_at',
        'fulfilled_at', 'cancelled_at', 'created_by', 'closed_by', 'remarks',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'expires_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function bookCopy()
    {
        return $this->belongsTo(BookCopy::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
