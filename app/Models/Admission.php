<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Admission extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'application_no',
        'name',
        'father_name',
        'dob',
        'gender',
        'mobile',
        'email',
        'address',
        'study_slot_id',
        'fee_plan_id',
        'wants_locker',
        'status',
        'remarks',
    ];

    protected $casts = [
        'dob' => 'date',
        'wants_locker' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function studySlot(): BelongsTo
    {
        return $this->belongsTo(StudySlot::class);
    }

    public function feePlan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class);
    }
}
