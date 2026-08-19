<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'user_id',
        'student_code',
        'qr_token',
        'portal_activation_token',
        'portal_activation_expires_at',
        'portal_activated_at',
        'name',
        'father_name',
        'mother_name',
        'dob',
        'gender',
        'mobile',
        'alternate_mobile',
        'email',
        'address',
        'photo',
        'id_proof_type',
        'id_proof_no',
        'guardian_name',
        'guardian_mobile',
        'joining_date',
        'status',
    ];

    protected $hidden = [
        'portal_activation_token',
    ];

    protected $casts = [
        'dob' => 'date',
        'joining_date' => 'date',
        'portal_activation_expires_at' => 'datetime',
        'portal_activated_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function memberships()
    {
        return $this->hasMany(StudentMembership::class);
    }

    public function activeMembership()
    {
        return $this->hasOne(StudentMembership::class)
            ->where('status', 'active')
            ->latestOfMany();
    }

    public function seatAllocations()
    {
        return $this->hasMany(SeatAllocation::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function bookIssues()
    {
        return $this->hasMany(BookIssue::class);
    }
}
