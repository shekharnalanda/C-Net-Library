<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffShift extends Model
{
    use HasFactory;

    protected $fillable = ['branch_id', 'name', 'start_time', 'end_time', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function attendances() { return $this->hasMany(StaffAttendance::class); }
}
