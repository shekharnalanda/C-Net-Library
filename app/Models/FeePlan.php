<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'study_slot_id',
        'name',
        'monthly_fee',
        'quarterly_fee',
        'half_yearly_fee',
        'yearly_fee',
        'admission_fee',
        'registration_fee',
        'security_deposit',
        'late_fee',
        'validity_days',
        'status',
    ];

    protected $casts = [
        'monthly_fee' => 'decimal:2',
        'quarterly_fee' => 'decimal:2',
        'half_yearly_fee' => 'decimal:2',
        'yearly_fee' => 'decimal:2',
        'admission_fee' => 'decimal:2',
        'registration_fee' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function studySlot()
    {
        return $this->belongsTo(StudySlot::class);
    }

    public function memberships()
    {
        return $this->hasMany(StudentMembership::class);
    }
}
