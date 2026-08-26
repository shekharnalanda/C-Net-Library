<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Locker extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'locker_no',
        'location',
        'monthly_charge',
        'status',
    ];

    protected $casts = [
        'monthly_charge' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function allocations()
    {
        return $this->hasMany(LockerAllocation::class);
    }
}
