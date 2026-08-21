<?php

namespace App\Http\Middleware;

use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\Payroll;
use App\Models\StaffLeave;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isGlobalAdmin()) {
            return $next($request);
        }

        $branchId = $user->branch_id;
        abort_unless($branchId, 403, 'No branch is assigned to this account.');

        if ($request->filled('branch_id')) {
            abort_unless((int) $request->input('branch_id') === (int) $branchId, 403);
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            // A route-bound model that explicitly carries branch_id must belong to
            // the current branch. A null branch is global/unassigned data and must
            // not become writable/readable to a branch account just because null
            // previously resolved as "no scope information".
            if (array_key_exists('branch_id', $parameter->getAttributes())) {
                $modelBranchId = $parameter->getAttribute('branch_id');
                abort_unless(
                    $modelBranchId !== null && (int) $modelBranchId === (int) $branchId,
                    403
                );

                continue;
            }

            $modelBranchId = $this->resolveRelatedBranchId($parameter);
            if ($modelBranchId !== null) {
                abort_unless((int) $modelBranchId === (int) $branchId, 403);
            }
        }

        $request->attributes->set('admin_branch_id', (int) $branchId);

        return $next($request);
    }

    private function resolveRelatedBranchId(Model $model): ?int
    {
        if ($model instanceof Payment) {
            return $model->student?->branch_id !== null ? (int) $model->student->branch_id : null;
        }

        if ($model instanceof PaymentAdjustment) {
            return $model->payment?->student?->branch_id !== null
                ? (int) $model->payment->student->branch_id
                : null;
        }

        if ($model instanceof StaffLeave || $model instanceof Payroll) {
            return $model->staff?->branch_id !== null
                ? (int) $model->staff->branch_id
                : null;
        }

        if (method_exists($model, 'student')) {
            $student = $model->getRelationValue('student');
            if ($student?->branch_id !== null) {
                return (int) $student->branch_id;
            }
        }

        if (method_exists($model, 'staff')) {
            $staff = $model->getRelationValue('staff');
            if ($staff?->branch_id !== null) {
                return (int) $staff->branch_id;
            }
        }

        return null;
    }
}
