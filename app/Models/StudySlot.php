<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudySlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'duration_hours',
        'start_time',
        'end_time',
        'is_24x7',
        'is_flexible',
        'status',
    ];

    protected $casts = [
        'is_24x7' => 'boolean',
        'is_flexible' => 'boolean',
        'status' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function feePlans()
    {
        return $this->hasMany(FeePlan::class);
    }

    public function memberships()
    {
        return $this->hasMany(StudentMembership::class);
    }

    public function seatAllocations()
    {
        return $this->hasMany(SeatAllocation::class);
    }
}
