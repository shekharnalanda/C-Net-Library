<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'mobile',
        'email',
        'address',
        'city',
        'state',
        'opening_time',
        'closing_time',
        'is_24x7',
        'status',
    ];

    protected $casts = [
        'is_24x7' => 'boolean',
        'status' => 'boolean',
    ];

    public function studyHalls()
    {
        return $this->hasMany(StudyHall::class);
    }

    public function studySlots()
    {
        return $this->hasMany(StudySlot::class);
    }

    public function feePlans()
    {
        return $this->hasMany(FeePlan::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
