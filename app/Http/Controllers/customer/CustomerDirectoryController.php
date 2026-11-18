<?php
namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use App\Models\{CustomerDirectoryProfile,Logo,WebsiteBanner,WebsiteFooter};
use App\Policies\CustomerDirectoryPolicy;
use App\Services\CustomerDirectoryService;
use Illuminate\Http\Request;
class CustomerDirectoryController extends Controller
{
    public function __construct(){ $this->middleware(function($request,$next){abort_unless(app(CustomerDirectoryPolicy::class)->manage($request->user(),app('customer')->id),403);return $next($request);}); }
    public function edit(){
        $id=app('customer')->id;
        return view('customer.directory.edit',['profile'=>CustomerDirectoryProfile::where('customer_id',$id)->first(),
            'suggestion'=>strip_tags(WebsiteFooter::where('customer_id',$id)->value('description')??''),
            'logos'=>Logo::where('customer_id',$id)->where('status',1)->get(['id','name']),
            'banners'=>WebsiteBanner::where('customer_id',$id)->where('status',1)->get(['id','title']),
            'branches'=>app('customer')->branches()->where('status',true)->orderBy('id')->get(),
            'publicContact'=>\App\Models\WebsiteTopbar::where('customer_id',$id)->where('enabled',true)->first(['phone','email']),
            'publicSocials'=>\App\Models\WebsiteSocialSetting::where('customer_id',$id)->first()]);
    }
    public function update(Request $r,CustomerDirectoryService $service){
        $data=$r->validate(['revision'=>'required|integer|min:0','description'=>'required|string|max:1500',
            'commune'=>'nullable|string|max:100',
            'show_public_contact'=>'sometimes|boolean','show_public_social_links'=>'sometimes|boolean',
            'branches'=>'sometimes|array|max:1000',
            'branches.*'=>'required|array:id,publication_enabled,commune,region',
            'branches.*.id'=>'required|integer|distinct',
            'branches.*.publication_enabled'=>'required|boolean',
            'branches.*.commune'=>'nullable|string|max:100',
            'branches.*.region'=>'nullable|string|max:100',
            'publication_enabled'=>'required|boolean','consent'=>'sometimes|accepted',
            'brand_display'=>'sometimes|required|in:both,name,logo','cover_source'=>'sometimes|required|in:banner,uploaded,default',
            'latitude'=>'nullable|required_with:longitude|numeric|between:-90,90','longitude'=>'nullable|required_with:latitude|numeric|between:-180,180',
            'cover'=>'nullable|file|image|mimes:png,jpg,jpeg,webp|max:4096|dimensions:max_width=4096,max_height=4096',
            'logo_id'=>'nullable|integer','website_banner_id'=>'nullable|integer','public_address'=>'nullable|string|max:255','city'=>'nullable|string|max:100','region'=>'nullable|string|max:100']);
        $newPath=null;
        try {
            if($r->hasFile('cover')){
                $image=@imagecreatefromstring(file_get_contents($r->file('cover')->getRealPath()));
                if(!$image)throw \Illuminate\Validation\ValidationException::withMessages(['cover'=>'No se pudo procesar la imagen.']);
                ob_start();imagepng($image);$bytes=ob_get_clean();imagedestroy($image);
                $newPath='directory/customer_'.app('customer')->id.'/'.\Illuminate\Support\Str::uuid().'.png';
                if(!\Illuminate\Support\Facades\Storage::disk('public')->put($newPath,$bytes))throw new \RuntimeException('No se pudo guardar la portada.');
                $data['cover_path']=$newPath;$data['cover_source']='uploaded';
            }
            $service->save($r->user(),app('customer'),$data);
        }catch(\Throwable $e){
            if($newPath)\Illuminate\Support\Facades\Storage::disk('public')->delete($newPath);
            throw $e;
        }
        return back()->with('success','Configuración del directorio actualizada.');
    }
}
