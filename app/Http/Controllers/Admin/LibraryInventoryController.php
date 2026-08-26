<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LibraryInventoryController extends Controller
{
    public function index(Request $request): View
    {
        $branches = Branch::query()->where('status', true)
            ->when(! $request->user()->isGlobalAdmin(), fn ($q) => $q->whereKey($request->user()->branch_id))
            ->orderBy('name')->get();

        $categories = BookCategory::query()->withCount('books')->orderBy('name')->get();
        $books = Book::query()->with('category')->withCount('copies')->orderBy('title')->get();
        $copies = BookCopy::query()->with(['book.category','branch'])->withCount(['issues','reservations'])
            ->when(! $request->user()->isGlobalAdmin(), fn ($q) => $q->where('branch_id', $request->user()->branch_id))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->q);
                $q->where(fn ($x) => $x->where('accession_no','like',"%{$term}%")
                    ->orWhere('barcode','like',"%{$term}%")
                    ->orWhere('rack_no','like',"%{$term}%")
                    ->orWhereHas('book', fn ($b) => $b->where('title','like',"%{$term}%")->orWhere('author','like',"%{$term}%")));
            })->orderByDesc('id')->paginate(40)->withQueryString();

        return view('admin.library.inventory', compact('branches','categories','books','copies'));
    }

    public function storeCategory(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['name'=>['required','string','max:150'],'slug'=>['nullable','alpha_dash','max:150','unique:book_categories,slug'],'status'=>['nullable','boolean']]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        if (BookCategory::where('slug',$data['slug'])->exists()) throw ValidationException::withMessages(['slug'=>'Category slug already exists.']);
        $data['status'] = $request->boolean('status', true);
        $category = BookCategory::create($data); $audit->log('library.category.created',$category,[],$category->toArray());
        return back()->with('success','Book category created.');
    }

    public function updateCategory(Request $request, BookCategory $category, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['name'=>['required','string','max:150'],'slug'=>['required','alpha_dash','max:150',Rule::unique('book_categories','slug')->ignore($category->id)],'status'=>['nullable','boolean']]);
        $data['status']=$request->boolean('status'); $old=$category->toArray(); $category->update($data); $audit->log('library.category.updated',$category,$old,$category->toArray());
        return back()->with('success','Book category updated.');
    }

    public function destroyCategory(BookCategory $category, AuditService $audit): RedirectResponse
    {
        if ($category->books()->exists()) { $category->update(['status'=>false]); return back()->withErrors(['category'=>'Category has books, so delete is blocked. It has been disabled instead.']); }
        $old=$category->toArray(); $category->delete(); $audit->log('library.category.deleted',$category,$old,[]); return back()->with('success','Unused category deleted.');
    }

    public function storeBook(Request $request, AuditService $audit): RedirectResponse
    {
        $data=$this->bookData($request); $book=Book::create($data); $audit->log('library.book.created',$book,[],$book->toArray()); return back()->with('success','Book master created.');
    }

    public function updateBook(Request $request, Book $book, AuditService $audit): RedirectResponse
    {
        $data=$this->bookData($request,$book); $old=$book->toArray(); $book->update($data); $audit->log('library.book.updated',$book,$old,$book->toArray()); return back()->with('success','Book master updated.');
    }

    public function destroyBook(Book $book, AuditService $audit): RedirectResponse
    {
        if ($book->copies()->exists()) { $book->update(['status'=>false]); return back()->withErrors(['book'=>'Book has inventory copies, so delete is blocked. It has been disabled instead.']); }
        $old=$book->toArray(); $book->delete(); $audit->log('library.book.deleted',$book,$old,[]); return back()->with('success','Unused book master deleted.');
    }

    public function storeCopy(Request $request, AuditService $audit): RedirectResponse
    {
        $data=$this->copyData($request); $this->assertBranch($request,(int)$data['branch_id']); $copy=BookCopy::create($data); $audit->log('library.copy.created',$copy,[],$copy->toArray()); return back()->with('success','Book copy created.');
    }

    public function bulkStoreCopies(Request $request, AuditService $audit): RedirectResponse
    {
        $data=$request->validate(['book_id'=>['required','exists:books,id'],'branch_id'=>['required','exists:branches,id'],'accession_prefix'=>['required','string','max:80'],'barcode_prefix'=>['nullable','string','max:80'],'start_no'=>['required','integer','min:1','max:999999'],'count'=>['required','integer','min:1','max:200'],'padding'=>['required','integer','min:1','max:6'],'rack_no'=>['nullable','string','max:100'],'condition'=>['required',Rule::in(['new','good','fair','damaged'])]]);
        $this->assertBranch($request,(int)$data['branch_id']);
        $created=DB::transaction(function() use($data){$n=0;for($i=0;$i<$data['count'];$i++){ $num=str_pad((string)($data['start_no']+$i),(int)$data['padding'],'0',STR_PAD_LEFT);$acc=$data['accession_prefix'].$num;$barcode=filled($data['barcode_prefix']??null)?$data['barcode_prefix'].$num:null;if(BookCopy::where('accession_no',$acc)->exists()||($barcode&&BookCopy::where('barcode',$barcode)->exists())) throw ValidationException::withMessages(['accession_prefix'=>"Duplicate accession/barcode detected at {$acc}."]);BookCopy::create(['book_id'=>$data['book_id'],'branch_id'=>$data['branch_id'],'accession_no'=>$acc,'barcode'=>$barcode,'rack_no'=>$data['rack_no']??null,'condition'=>$data['condition'],'status'=>'available']);$n++;}return $n;},3);
        $audit->log('library.copy.bulk_created',null,[],['book_id'=>$data['book_id'],'branch_id'=>$data['branch_id'],'count'=>$created,'accession_prefix'=>$data['accession_prefix']]); return back()->with('success',"{$created} book copies created.");
    }

    public function updateCopy(Request $request, BookCopy $copy, AuditService $audit): RedirectResponse
    {
        $this->assertBranch($request,(int)$copy->branch_id); $data=$this->copyData($request,$copy); $this->assertBranch($request,(int)$data['branch_id']);
        if ($copy->status==='issued' && $data['status']!=='issued') throw ValidationException::withMessages(['status'=>'Issued copy status must be changed through Return/Lost workflow.']);
        if ($copy->status==='reserved' && $data['status']!=='reserved') throw ValidationException::withMessages(['status'=>'Reserved copy status must be changed through reservation workflow.']);
        $old=$copy->toArray();$copy->update($data);$audit->log('library.copy.updated',$copy,$old,$copy->toArray());return back()->with('success','Book copy updated.');
    }

    public function destroyCopy(Request $request, BookCopy $copy, AuditService $audit): RedirectResponse
    {
        $this->assertBranch($request,(int)$copy->branch_id); $copy->loadCount(['issues','reservations']);
        if ($copy->issues_count || $copy->reservations_count || in_array($copy->status,['issued','reserved','lost'],true)) return back()->withErrors(['copy'=>'Permanent delete blocked because this copy has circulation/reservation history or an operational status.']);
        $old=$copy->toArray();$copy->delete();$audit->log('library.copy.deleted',$copy,$old,[]);return back()->with('success','Unused book copy deleted.');
    }

    private function bookData(Request $request, ?Book $book=null): array
    {
        $d=$request->validate(['book_category_id'=>['required','exists:book_categories,id'],'title'=>['required','string','max:255'],'author'=>['nullable','string','max:255'],'isbn'=>['nullable','string','max:50'],'publisher'=>['nullable','string','max:255'],'edition'=>['nullable','string','max:100'],'publication_year'=>['nullable','integer','min:1000','max:'.(now()->year+1)],'language'=>['nullable','string','max:100'],'description'=>['nullable','string','max:5000'],'status'=>['nullable','boolean']]);$d['status']=$request->boolean('status');return $d;
    }

    private function copyData(Request $request, ?BookCopy $copy=null): array
    {
        return $request->validate(['book_id'=>['required','exists:books,id'],'branch_id'=>['required','exists:branches,id'],'accession_no'=>['required','string','max:100',Rule::unique('book_copies','accession_no')->ignore($copy?->id)],'barcode'=>['nullable','string','max:100',Rule::unique('book_copies','barcode')->ignore($copy?->id)],'rack_no'=>['nullable','string','max:100'],'condition'=>['required',Rule::in(['new','good','fair','damaged'])],'status'=>['required',Rule::in(['available','issued','reserved','lost','damaged'])]]);
    }

    private function assertBranch(Request $request, int $branchId): void
    { abort_unless($request->user()->isGlobalAdmin() || (int)$request->user()->branch_id===$branchId,403); }
}
