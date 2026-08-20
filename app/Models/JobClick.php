<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobClick extends Model
{
    protected $fillable = [
        'job_id',
        'student_id',
        'ip_address',
        'user_agent',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
