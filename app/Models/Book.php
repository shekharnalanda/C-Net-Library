<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_category_id','title','author','isbn','publisher','edition',
        'publication_year','language','description','cover_image','status',
    ];

    protected $casts = ['status' => 'boolean'];

    public function category()
    {
        return $this->belongsTo(BookCategory::class, 'book_category_id');
    }

    public function copies()
    {
        return $this->hasMany(BookCopy::class);
    }
}
