<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminBranchScope
{
    public function apply(Builder $query, ?User $user, string $column = 'branch_id'): Builder
    {
        if (! $user || $user->isGlobalAdmin()) {
            return $query;
        }

        return $user->branch_id === null
            ? $query
            : $query->where($column, (int) $user->branch_id);
    }

    public function id(?User $user): ?int
    {
        if (! $user || $user->isGlobalAdmin() || $user->branch_id === null) {
            return null;
        }

        return (int) $user->branch_id;
    }
}
