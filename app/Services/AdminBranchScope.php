<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminBranchScope
{
    public function apply(Builder $query, ?User $user, string $column = 'branch_id'): Builder
    {
        if (! $user) {
            // Administrative queries must never silently become unscoped when
            // authentication context is unexpectedly absent.
            return $query->whereRaw('1 = 0');
        }

        if ($user->isGlobalAdmin()) {
            return $query;
        }

        if ($user->branch_id === null) {
            // EnsureBranchScope normally rejects this account before a controller
            // runs. Keep this service fail-closed as defence in depth for console,
            // tests, future routes, or accidental direct service usage.
            return $query->whereRaw('1 = 0');
        }

        return $query->where($column, (int) $user->branch_id);
    }

    public function id(?User $user): ?int
    {
        if (! $user || $user->isGlobalAdmin()) {
            return null;
        }

        return $user->branch_id !== null ? (int) $user->branch_id : null;
    }
}
