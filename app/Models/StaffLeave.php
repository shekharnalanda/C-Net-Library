<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffLeave extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id', 'from_date', 'to_date', 'leave_type', 'reason', 'status',
        'approved_by', 'admin_remarks',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    public function staff() { return $this->belongsTo(Staff::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
