<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminBranchScope
{
    public static function id(Request $request): ?int
    {
        $user = $request->user();

        if (! $user || $user->isGlobalAdmin()) {
            return null;
        }

        return $user->branch_id !== null ? (int) $user->branch_id : null;
    }

    public static function apply(Builder $query, Request $request, string $column = 'branch_id'): Builder
    {
        $branchId = self::id($request);

        return $branchId === null ? $query : $query->where($column, $branchId);
    }
}
