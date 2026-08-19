<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryItem extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'image_path', 'alt_text', 'sort_order', 'status'];

    protected $casts = ['status' => 'boolean'];
}
