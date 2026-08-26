<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StudySlot;
use App\Support\AdminBranchScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SlotController extends Controller
{
    public function index(Request $request): View
    {
        $user=$request->user();
        $branches=Branch::query()->where('status',true)->when(!$user->isGlobalAdmin(),fn($q)=>$q->whereKey($user->branch_id))->orderBy('name')->get();
        $branchIds=$branches->pluck('id');
        $selectedBranchId=$request->integer('branch_id')?:null;
        if(!$user->isGlobalAdmin())$selectedBranchId=(int)$user->branch_id;
        if($selectedBranchId&&!$branchIds->contains($selectedBranchId))abort(403);
        $slots=StudySlot::query()->whereIn('branch_id',$selectedBranchId?[$selectedBranchId]:$branchIds)->with('branch:id,name')->withCount(['feePlans','memberships','seatAllocations'])->orderBy('branch_id')->orderBy('duration_hours')->orderBy('name')->get();
        return view('admin.study-space.slots',compact('branches','slots','selectedBranchId'));
    }
}
