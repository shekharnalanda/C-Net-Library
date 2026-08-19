<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'title',
        'organization',
        'job_type',
        'category',
        'qualification',
        'location',
        'published_date',
        'last_date',
        'summary',
        'official_url',
        'is_featured',
        'status',
    ];

    protected $casts = [
        'published_date' => 'date',
        'last_date' => 'date',
        'is_featured' => 'boolean',
        'status' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function savedByStudents()
    {
        return $this->belongsToMany(Student::class, 'saved_jobs')->withTimestamps();
    }
}
