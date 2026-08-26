<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DigitalResource;
use App\Models\DigitalResourceLog;
use App\Services\AdminBranchScope;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DigitalResourceController extends Controller
{
    public function index(Request $request, AdminBranchScope $branchScope)
    {
        $baseQuery=$branchScope->apply(DigitalResource::query(),$request->user());
        $summary=['total'=>(clone $baseQuery)->count(),'active'=>(clone $baseQuery)->where('status',true)->count(),'public'=>(clone $baseQuery)->where('access_type','public')->count(),'members'=>(clone $baseQuery)->where('access_type','members')->count(),'premium'=>(clone $baseQuery)->where('access_type','premium')->count()];
        $resourceIds=(clone $baseQuery)->pluck('id');$summary['views']=DigitalResourceLog::query()->whereIn('digital_resource_id',$resourceIds)->where('action','view')->count();$summary['downloads']=DigitalResourceLog::query()->whereIn('digital_resource_id',$resourceIds)->where('action','download')->count();
        $resources=(clone $baseQuery)->with('branch')->withCount(['logs','logs as views_count'=>fn($q)=>$q->where('action','view'),'logs as downloads_count'=>fn($q)=>$q->where('action','download')])
            ->when($request->filled('search'),function($q)use($request){$search=trim((string)$request->string('search'));$q->where(fn($x)=>$x->where('title','like',"%{$search}%")->orWhere('category','like',"%{$search}%")->orWhere('resource_type','like',"%{$search}%"));})
            ->when($request->filled('type'),fn($q)=>$q->where('resource_type',$request->string('type')))->when($request->filled('access'),fn($q)=>$q->where('access_type',$request->string('access')))->when($request->filled('status'),fn($q)=>$q->where('status',$request->string('status')==='active'))->latest()->paginate(20)->withQueryString();
        $branches=Branch::query()->where('status',true);if(!$request->user()->isGlobalAdmin())$branches->whereKey($request->user()->branch_id);
        return view('admin.digital-resources.index',['resources'=>$resources,'branches'=>$branches->orderBy('name')->get(),'summary'=>$summary]);
    }

    public function store(Request $request,AuditService $audit)
    {
        $data=$this->validatedData($request);if(!$request->user()->isGlobalAdmin())$data['branch_id']=$request->user()->branch_id;
        if(!$request->hasFile('resource_file')&&empty($data['external_url']))return back()->withErrors(['resource'=>'Upload a file or provide an external URL.'])->withInput();
        $this->validateSourceChoice($request,$data);$filePath=$this->storeUploadedFile($request);$slug=$this->uniqueSlug($data['title']);
        $resource=DigitalResource::create(['branch_id'=>$data['branch_id']??null,'title'=>$data['title'],'slug'=>$slug,'resource_type'=>$data['resource_type'],'category'=>$data['category']??null,'description'=>$data['description']??null,'file_path'=>$filePath,'external_url'=>$data['external_url']??null,'access_type'=>$data['access_type'],'download_allowed'=>$request->boolean('download_allowed'),'status'=>true,'uploaded_by'=>auth()->id()]);
        $audit->log('digital-resource.created',$resource,[],$resource->only(['title','resource_type','file_path','external_url','access_type','download_allowed','status']));return back()->with('success','Digital resource added successfully.');
    }

    public function update(Request $request,DigitalResource $resource,AuditService $audit)
    {
        $this->assertResourceBranch($request,$resource);$data=$this->validatedData($request);if(!$request->user()->isGlobalAdmin())$data['branch_id']=$request->user()->branch_id;$this->validateSourceChoice($request,$data,$resource);
        $old=$resource->toArray();$oldFilePath=$resource->file_path;$newFilePath=$this->storeUploadedFile($request);
        $resource->update(['branch_id'=>$data['branch_id']??null,'title'=>$data['title'],'slug'=>$this->uniqueSlug($data['title'],$resource->id),'resource_type'=>$data['resource_type'],'category'=>$data['category']??null,'description'=>$data['description']??null,'file_path'=>$newFilePath?:($data['external_url']??null?null:$oldFilePath),'external_url'=>$data['external_url']??null,'access_type'=>$data['access_type'],'download_allowed'=>$request->boolean('download_allowed'),'status'=>$request->boolean('status')]);
        if($newFilePath&&$oldFilePath&&$oldFilePath!==$newFilePath)$this->deletePrivateFile($oldFilePath);if(!empty($data['external_url'])&&$oldFilePath)$this->deletePrivateFile($oldFilePath);
        $audit->log('digital-resource.updated',$resource,$old,$resource->fresh()->toArray());return back()->with('success','Digital resource updated successfully.');
    }

    public function destroy(Request $request,DigitalResource $resource,AuditService $audit)
    {
        $this->assertResourceBranch($request,$resource);$resource->loadCount('logs');
        if($resource->logs_count>0){$old=$resource->only(['status']);$resource->update(['status'=>false]);$audit->log('digital-resource.disabled',$resource,$old,['status'=>false],$request);return back()->withErrors(['resource'=>'Usage history exists ('.$resource->logs_count.' events). Permanent delete is blocked; the resource has been disabled instead.']);}
        $old=$resource->toArray();$filePath=$resource->file_path;$audit->log('digital-resource.deleted',$resource,$old,[],$request);$resource->delete();if($filePath)$this->deletePrivateFile($filePath);return back()->with('success','Unused digital resource deleted successfully.');
    }

    private function validatedData(Request $request):array{return $request->validate(['branch_id'=>['nullable','exists:branches,id'],'title'=>['required','string','max:255'],'resource_type'=>['required','in:pdf,ebook,notes,question_paper,video,link'],'category'=>['nullable','string','max:255'],'description'=>['nullable','string'],'resource_file'=>['nullable','file','max:51200','mimes:pdf,epub,txt,doc,docx,ppt,pptx,xls,xlsx,mp4,webm','mimetypes:application/pdf,application/epub+zip,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,video/mp4,video/webm'],'external_url'=>['nullable','url','max:1000'],'access_type'=>['required','in:public,members,premium'],'download_allowed'=>['nullable','boolean'],'status'=>['nullable','boolean']]);}
    private function validateSourceChoice(Request $request,array $data,?DigitalResource $resource=null):void{if($request->hasFile('resource_file')&&!empty($data['external_url']))throw ValidationException::withMessages(['resource'=>'Choose either a file upload or an external URL, not both.']);if(!empty($data['external_url'])){$scheme=strtolower((string)parse_url($data['external_url'],PHP_URL_SCHEME));if(!in_array($scheme,['http','https'],true))throw ValidationException::withMessages(['external_url'=>'External resource URLs must use HTTP or HTTPS.']);}if($data['resource_type']==='link'&&$request->hasFile('resource_file'))throw ValidationException::withMessages(['resource_file'=>'Link resources must use an external URL.']);$hasExistingFile=$resource?->file_path&&empty($data['external_url']);$hasExistingUrl=$resource?->external_url&&!$request->hasFile('resource_file');if(!$request->hasFile('resource_file')&&empty($data['external_url'])&&!$hasExistingFile&&!$hasExistingUrl)throw ValidationException::withMessages(['resource'=>'Upload a file or provide an external URL.']);if($data['resource_type']==='link'&&empty($data['external_url'])&&!$hasExistingUrl)throw ValidationException::withMessages(['external_url'=>'Link resources require an external URL.']);}
    private function storeUploadedFile(Request $request):?string{if(!$request->hasFile('resource_file'))return null;$file=$request->file('resource_file');$extension=strtolower($file->getClientOriginalExtension());$fileName=Str::uuid().($extension?'.'.$extension:'');return $file->storeAs('digital-resources',$fileName,'local');}
    private function deletePrivateFile(string $path):void{$normalized=ltrim(str_replace('\\','/',$path),'/');if(!str_starts_with($normalized,'digital-resources/')||str_contains($normalized,'../'))return;Storage::disk('local')->delete($normalized);}
    private function assertResourceBranch(Request $request,DigitalResource $resource):void{abort_unless($request->user()->isGlobalAdmin()||((int)$resource->branch_id===(int)$request->user()->branch_id),403);}
    private function uniqueSlug(string $title,?int $ignoreId=null):string{$baseSlug=Str::slug($title)?:'resource';$slug=$baseSlug;$counter=2;while(DigitalResource::query()->where('slug',$slug)->when($ignoreId,fn($q)=>$q->whereKeyNot($ignoreId))->exists())$slug=$baseSlug.'-'.$counter++;return $slug;}
}
