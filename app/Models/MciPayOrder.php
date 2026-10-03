<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MciPayOrder extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected function casts(): array { return ['metadata'=>'array','amount_paise'=>'integer','revision'=>'integer','payment_date'=>'date']; }
}

