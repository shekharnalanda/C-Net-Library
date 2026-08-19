<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DigitalResourceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'digital_resource_id',
        'student_id',
        'action',
        'accessed_at',
        'ip_address',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];

    public function resource()
    {
        return $this->belongsTo(DigitalResource::class, 'digital_resource_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
