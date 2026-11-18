<?php
namespace App\Http\Controllers\superadmin;
use App\Http\Controllers\Controller;
use App\Models\CustomerDirectoryProfile;
use App\Policies\CustomerDirectoryPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CustomerDirectoryController extends Controller
{
    public function __construct(){ $this->middleware(function($r,$next){abort_unless(app(CustomerDirectoryPolicy::class)->moderate($r->user()),403);return $next($r);}); }
    public function index(){return view('superadmin.directory.index',['profiles'=>CustomerDirectoryProfile::with('customer')->orderByDesc('id')->paginate(20)]);}
    public function update(Request $r,int $id){
        $data=$r->validate(['suspend'=>'required|boolean','reason'=>'required|string|max:2000','revision'=>'required|integer|min:1']);
        DB::transaction(function()use($r,$id,$data){
            $p=CustomerDirectoryProfile::lockForUpdate()->findOrFail($id);
            abort_unless($p->revision===$r->integer('revision'),409,'La publicación cambió. Recarga antes de moderar.');
            $p->update(['suspended_at'=>$data['suspend']?now():null,'suspended_by'=>$r->user()->id,'suspension_reason'=>$data['reason'],'revision'=>$p->revision+1]);
            DB::table('customer_directory_events')->insert(['customer_id'=>$p->customer_id,'actor_id'=>$r->user()->id,'action'=>$data['suspend']?'suspended':'suspension_lifted','details'=>json_encode(['reason'=>$data['reason'],'revision'=>$p->revision]),'created_at'=>now()]);
        });return back()->with('success','Moderación de publicación actualizada.');
    }
}
