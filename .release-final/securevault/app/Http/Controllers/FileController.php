<?php
namespace App\Http\Controllers;
use App\Models\{VaultFile,Category,Setting};
use App\Services\{Audit,FileStorage};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate,DB,Storage};
class FileController extends Controller {
    public function index(Request $r) {
        $query = VaultFile::with(['category','user']); $admin = $r->routeIs('admin.files');
        if (!$admin) $query->where('user_id',$r->user()->id);
        if ($r->filled('q')) $query->where('title','like','%'.mb_substr($r->string('q'),0,120).'%');
        if ($r->filled('category')) $query->where('category_id',$r->integer('category'));
        if ($admin && $r->filled('user')) $query->where('user_id',$r->integer('user'));
        return view('files.index',['files'=>$query->latest()->paginate(18)->withQueryString(),'categories'=>Category::orderBy('name')->get(),'admin'=>$admin]);
    }
    public function create() { return view('files.upload',['categories'=>Category::where('enabled',true)->orderBy('name')->get()]); }
    public function store(Request $r,FileStorage $storage) {
        abort_unless($r->user()->hasPermission('files.upload'),403);
        $max = (int)Setting::read('max_upload_mb',100)*1024;
        $data = $r->validate(['title'=>'required|string|max:160','description'=>'nullable|string|max:4000','category_id'=>'required|integer|exists:categories,id','file'=>'required|file|max:'.$max]);
        $file = $storage->upload($r->user(),$r->file('file'),Category::findOrFail($data['category_id']),$data);
        return redirect()->route('files.show',$file)->with('status','File securely stored.');
    }
    private function authorizeView(VaultFile $file): void {
        if (Gate::denies('view',$file)) { Audit::record('unauthorized_file','Denied file access: '.$file->id,null,'security_logs'); abort(404); }
    }
    public function show(Request $r,VaultFile $file) {
        $this->authorizeView($file);
        DB::table('file_views')->insert(['file_id'=>$file->id,'user_id'=>$r->user()->id,'ip_address'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]);
        Audit::record('file_view','Viewed file '.$file->id);
        $siblings = VaultFile::where('user_id',$file->user_id)->where('category_id',$file->category_id);
        $previous = (clone $siblings)->where('created_at','<',$file->created_at)->latest()->first();
        $next = (clone $siblings)->where('created_at','>',$file->created_at)->oldest()->first();
        return view('files.show',compact('file','previous','next'));
    }
    public function content(Request $r,VaultFile $file) {
        $this->authorizeView($file);
        abort_unless(Storage::disk('local')->exists($file->disk_path),404);
        // Symfony BinaryFileResponse supplies byte-range and HEAD support for authenticated media.
        $response = response()->file(Storage::disk('local')->path($file->disk_path),[
            'Content-Type'=>$file->mime,'Content-Disposition'=>'inline; filename="preview.'.$file->extension.'"',
            'Cache-Control'=>'private, no-store, max-age=0','X-Content-Type-Options'=>'nosniff',
            'Content-Security-Policy'=>"default-src 'none'; frame-ancestors 'self'; sandbox allow-same-origin",
        ]);
        Audit::record('media_request','Requested protected content '.$file->id);
        return $response;
    }
    public function destroy(Request $r,VaultFile $file,FileStorage $storage) { Gate::authorize('delete',$file); $storage->delete($file,$r->user()); return redirect()->route('admin.files')->with('status','File removed from the vault. Retained bytes follow the security retention policy.'); }
}
