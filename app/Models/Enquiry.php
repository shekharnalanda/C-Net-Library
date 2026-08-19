<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'assigned_to',
        'enquiry_no',
        'name',
        'mobile',
        'email',
        'source',
        'interested_plan',
        'message',
        'status',
        'follow_up_date',
        'follow_up_notes',
        'converted_admission_id',
    ];

    protected $casts = [
        'follow_up_date' => 'date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedAdmission()
    {
        return $this->belongsTo(Admission::class, 'converted_admission_id');
    }
}
