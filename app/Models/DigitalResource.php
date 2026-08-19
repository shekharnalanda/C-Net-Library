<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DigitalResource extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'title',
        'slug',
        'resource_type',
        'category',
        'description',
        'file_path',
        'external_url',
        'access_type',
        'download_allowed',
        'status',
        'uploaded_by',
    ];

    protected $casts = [
        'download_allowed' => 'boolean',
        'status' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function logs()
    {
        return $this->hasMany(DigitalResourceLog::class);
    }
}
