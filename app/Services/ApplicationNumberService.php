<?php

namespace App\Services;

use App\Models\Admission;
use Illuminate\Support\Str;

class ApplicationNumberService
{
    public function generate(): string
    {
        do {
            $number = 'CNL-ADM-'.now()->format('Y').'-'.Str::upper(Str::random(8));
        } while (Admission::query()->where('application_no', $number)->exists());

        return $number;
    }
}
