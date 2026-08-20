<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Str;

class ReceiptService
{
    public function __construct(
        private readonly SettingsService $settings
    ) {
    }

    public function generate(?string $prefix = null, ?int $branchId = null): string
    {
        $prefix ??= (string) $this->settings->get('receipt_prefix', 'CNL', $branchId);
        $year = now()->format('Y');

        do {
            $receipt = sprintf(
                '%s-%s-%s',
                $prefix,
                $year,
                strtoupper(Str::random(10))
            );
        } while (Payment::query()->where('receipt_no', $receipt)->exists());

        return $receipt;
    }
}
