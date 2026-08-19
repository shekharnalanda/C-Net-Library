<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookCopy extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id','branch_id','accession_no','barcode','rack_no','condition','status',
    ];

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function issues()
    {
        return $this->hasMany(BookIssue::class);
    }
}
