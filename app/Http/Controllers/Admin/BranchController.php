<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $branches = Branch::query()
            ->withCount(['students', 'studyHalls', 'studySlots', 'feePlans'])
            ->orderByDesc('status')->orderBy('name')->get();
        return view('admin.branches.index', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$this->validated($request); $data['code']=strtoupper(trim($data['code'])); $data['is_24x7']=$request->boolean('is_24x7'); $data['status']=$request->boolean('status',true); Branch::create($data);
        return back()->with('success','Branch created successfully. Now configure its study hall, seats, slots, fee plans and lockers.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $data=$this->validated($request,$branch); $data['code']=strtoupper(trim($data['code'])); $data['is_24x7']=$request->boolean('is_24x7'); $data['status']=$request->boolean('status'); $branch->update($data);
        return back()->with('success','Branch updated successfully.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $dependencies=[
            'students'=>$branch->students()->count(),
            'study halls'=>$branch->studyHalls()->count(),
            'study slots'=>$branch->studySlots()->count(),
            'fee plans'=>$branch->feePlans()->count(),
        ];
        $used=array_filter($dependencies,fn($count)=>$count>0);
        if($used!==[]){
            $details=collect($used)->map(fn($count,$label)=>$label.': '.$count)->implode(', ');
            throw ValidationException::withMessages(['branch_delete'=>'Branch cannot be deleted because operational records exist ('.$details.'). Disable the branch instead, or remove its unused setup first.']);
        }
        $name=$branch->name; $branch->delete();
        return back()->with('success','Branch deleted successfully: '.$name);
    }

    private function validated(Request $request, ?Branch $branch=null): array
    {
        return $request->validate([
            'name'=>['required','string','max:150'],
            'code'=>['required','string','max:30',Rule::unique('branches','code')->ignore($branch?->id)],
            'mobile'=>['nullable','string','max:30'],'email'=>['nullable','email','max:190'],'address'=>['nullable','string','max:1000'],'city'=>['nullable','string','max:100'],'state'=>['nullable','string','max:100'],'opening_time'=>['nullable','date_format:H:i'],'closing_time'=>['nullable','date_format:H:i'],'is_24x7'=>['nullable','boolean'],'status'=>['nullable','boolean'],
        ]);
    }
}
