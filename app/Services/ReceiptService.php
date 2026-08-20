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
        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($prefix, $branchId, $year) {
            $query = DB::table('receipt_sequences')
                ->where('year', $year)
                ->where(function ($query) use ($branchId) {
                    $branchId === null
                        ? $query->whereNull('branch_id')
                        : $query->where('branch_id', $branchId);
                });

            $sequence = $query->lockForUpdate()->first();

            if (! $sequence) {
                try {
                    DB::table('receipt_sequences')->insert([
                        'branch_id' => $branchId,
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

            return sprintf('%s-%d-%06d', $prefix, $year, $next);
        }, 3);
    }
}
