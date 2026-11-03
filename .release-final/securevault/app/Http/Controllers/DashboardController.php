<?php
namespace App\Http\Controllers;
use App\Models\{User,VaultFile,Category,StorageUsage};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class DashboardController extends Controller {
    public function index(Request $r) {
        $admin = $r->user()->isAdmin(); $q = VaultFile::query(); if (!$admin) $q->where('user_id',$r->user()->id);
        $stats = ['Files'=>(clone $q)->count(),'Images'=>(clone $q)->where('mime','like','image/%')->count(),'Screenshots'=>(clone $q)->whereHas('category',fn($q)=>$q->where('is_screenshot',true))->count(),'Videos'=>(clone $q)->where('mime','like','video/%')->count(),'Documents'=>(clone $q)->where('mime','not like','image/%')->where('mime','not like','video/%')->count()];
        $usage = StorageUsage::query(); if (!$admin) $usage->where('user_id',$r->user()->id);
        $stats['Storage used'] = number_format($usage->sum('bytes_used')/1048576,1).' MB';
        $users = $admin ? ['Total users'=>User::count(),'Active'=>User::where('status','active')->count(),'Pending'=>User::where('status','pending')->count(),'Suspended'=>User::where('status','suspended')->count(),'Blocked'=>User::where('status','blocked')->count(),'Registrations today'=>User::whereDate('created_at',today())->count(),'Uploads today'=>VaultFile::whereDate('created_at',today())->count()] : [];
        $series = []; for ($i=6;$i>=0;$i--) { $day = today()->subDays($i); $series[] = ['day'=>$day->format('D'),'files'=>(clone $q)->whereDate('created_at',$day)->count()]; }
        return view('dashboard',['admin'=>$admin,'stats'=>$stats,'userStats'=>$users,'recent'=>(clone $q)->with(['category','user'])->latest()->limit(6)->get(),'series'=>$series]);
    }
    public function categories() { return view('categories',['categories'=>Category::withCount(['files'=>fn($q)=>$q->where('user_id',auth()->id())])->where('enabled',true)->get()]); }
    public function activity(Request $r) {
        $admin = $r->routeIs('admin.activity'); $source = $r->query('source','user_activities');
        abort_unless(in_array($source,['user_activities','security_logs','login_histories','email_verification_logs'],true),404);
        if (!$admin) $source = 'user_activities';
        $data = $r->validate(['from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from','user'=>'nullable|integer','type'=>'nullable|string|max:100']);
        $q = DB::table($source); if (!$admin) $q->where('user_id',$r->user()->id); elseif ($r->filled('user')) $q->where('user_id',$data['user']);
        if ($r->filled('from')) $q->where('created_at','>=',$data['from'].' 00:00:00');
        if ($r->filled('to')) $q->where('created_at','<=',$data['to'].' 23:59:59');
        if ($r->filled('type')) $q->where('type',$data['type']);
        return view('activity',['activities'=>$q->orderByDesc('id')->paginate(40)->withQueryString(),'admin'=>$admin,'source'=>$source]);
    }
}
