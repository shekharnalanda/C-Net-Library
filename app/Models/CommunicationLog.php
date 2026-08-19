<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'student_id',
        'enquiry_id',
        'communication_template_id',
        'channel',
        'recipient',
        'subject',
        'message',
        'status',
        'provider',
        'provider_message_id',
        'failure_reason',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function enquiry()
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function template()
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
