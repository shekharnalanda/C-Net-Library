<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'title', 'menu_label', 'excerpt', 'content',
        'meta_title', 'meta_description', 'meta_keywords',
        'canonical_url', 'og_image', 'sort_order', 'show_in_menu', 'status',
    ];

    protected $casts = [
        'show_in_menu' => 'boolean',
        'status' => 'boolean',
    ];
}
