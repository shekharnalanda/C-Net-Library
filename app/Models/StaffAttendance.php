<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id', 'staff_shift_id', 'attendance_date', 'check_in', 'check_out',
        'status', 'worked_minutes', 'remarks',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
    ];

    public function staff() { return $this->belongsTo(Staff::class); }
    public function shift() { return $this->belongsTo(StaffShift::class, 'staff_shift_id'); }
}
