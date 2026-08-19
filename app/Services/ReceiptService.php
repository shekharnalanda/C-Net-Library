<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    public function generate(string $prefix = 'CNL'): string
    {
        return DB::transaction(function () use ($prefix) {
            $year = now()->format('Y');
            $lastId = (int) Payment::query()->lockForUpdate()->max('id');
            $sequence = str_pad((string) ($lastId + 1), 6, '0', STR_PAD_LEFT);

            return sprintf('%s-%s-%s', $prefix, $year, $sequence);
        });
    }
}
