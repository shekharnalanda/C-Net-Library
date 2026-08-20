<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReceiptService
{
    public function __construct(
        private readonly SettingsService $settings
    ) {
    }

    public function generate(?string $prefix = null, ?int $branchId = null): string
    {
        $prefix ??= (string) $this->settings->get('receipt_prefix', 'CNL', $branchId);
        $prefix = strtoupper(trim($prefix));
        $prefix = preg_replace('/[^A-Z0-9-]+/', '-', $prefix) ?: 'CNL';
        $prefix = trim($prefix, '-') ?: 'CNL';

        $year = (int) now()->format('Y');
        $series = $branchId === null ? 'GLOBAL' : 'B'.str_pad((string) $branchId, 6, '0', STR_PAD_LEFT);

        return DB::transaction(function () use ($prefix, $branchId, $year, $series) {
            $query = DB::table('receipt_sequences')
                ->where('series_key', $series)
                ->where('year', $year);

            $sequence = $query->lockForUpdate()->first();

            if (! $sequence) {
                try {
                    DB::table('receipt_sequences')->insert([
                        'branch_id' => $branchId,
                        'series_key' => $series,
                        'year' => $year,
                        'last_number' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Illuminate\Database\QueryException) {
                    // A concurrent transaction may have created the row first.
                }

                $sequence = $query->lockForUpdate()->firstOrFail();
            }

            $next = (int) $sequence->last_number + 1;

            $query->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

            return sprintf('%s-%s-%d-%06d', $prefix, $series, $year, $next);
        }, 3);
    }
}
