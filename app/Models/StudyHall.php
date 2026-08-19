<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudyHall extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'floor',
        'total_seats',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function seats()
    {
        return $this->hasMany(Seat::class);
    }
}
