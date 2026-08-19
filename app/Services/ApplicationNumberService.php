<?php

namespace App\Services;

use App\Models\Admission;

class ApplicationNumberService
{
    public function generate(): string
    {
        $nextId = (int) Admission::max('id') + 1;

        return sprintf('CNL-ADM-%s-%05d', now()->format('Y'), $nextId);
    }
}
