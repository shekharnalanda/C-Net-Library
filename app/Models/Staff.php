<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'branch_id', 'user_id', 'staff_code', 'name', 'role', 'mobile', 'email',
        'joining_date', 'monthly_salary', 'status',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'monthly_salary' => 'decimal:2',
    ];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function attendances() { return $this->hasMany(StaffAttendance::class); }
    public function leaves() { return $this->hasMany(StaffLeave::class); }
    public function payrolls() { return $this->hasMany(Payroll::class); }
}
