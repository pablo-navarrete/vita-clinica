<?php
namespace App\Http\Controllers;
use App\Models\VitaWebsiteSetting;
use App\Services\CustomerDirectoryService;
use Illuminate\Http\Request;
class PublicCustomerDirectoryController extends Controller
{
    private function site(): array {$site=VitaWebsiteSetting::content();abort_unless($site['published'],503);return $site;}
    public function index(Request $request,CustomerDirectoryService $service){
        $site=$this->site();$data=$request->validate(['buscar'=>'nullable|string|max:100','cercanas'=>'nullable|in:1','page'=>'nullable|integer|min:1|max:10000',
            'especialidad'=>['nullable','integer',\Illuminate\Validation\Rule::in(array_keys(config('specialties.catalog', [])))]]);
        $q=$service->eligibleQuery();
        $specialtyOptions=$service->specialtyOptions();
        $selectedSpecialty=isset($data['especialidad']) ? (int)$data['especialidad'] : null;
        $specialtyName=$selectedSpecialty ? app(\App\Services\SpecialtyCatalog::class)->name($selectedSpecialty) : null;
        if($selectedSpecialty) $service->filterBySpecialty($q,$selectedSpecialty);
        if($search=trim($data['buscar']??'')){
            $term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';
            $q->where(fn($q)=>$q->whereHas('customer',fn($c)=>$c->where('name','like',$term))->orWhere('commune','like',$term)->orWhere('city','like',$term)->orWhere('region','like',$term)->orWhere('public_address','like',$term));
        }
        $nearby=false;$locationExpired=false;
        if($request->boolean('cercanas')){
            $location=$request->session()->get('directory_location');
            if($location && $location['expires_at']>time()){
                $nearby=true;
                $q->select('customer_directory_profiles.*')->selectRaw('6371 * ACOS(LEAST(1,GREATEST(-1,SIN(RADIANS(?))*SIN(RADIANS(latitude))+COS(RADIANS(?))*COS(RADIANS(latitude))*COS(RADIANS(longitude)-RADIANS(?))))) AS distance_km',[$location['latitude'],$location['latitude'],$location['longitude']])
                    ->whereNotNull('latitude')->whereNotNull('longitude')->orderBy('distance_km');
            }else{$request->session()->forget('directory_location');$locationExpired=true;}
        }
        $profiles=$q->orderBy('id')->paginate(12)->withQueryString();
        return response()->view('vita.directory.index',compact('site','profiles','service','search','nearby','locationExpired','specialtyOptions','selectedSpecialty','specialtyName'))->header('Cache-Control','private, no-store');
    }
    public function location(Request $request){
        $this->site();
        $data=$request->validate(['latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180']);
        $request->session()->put('directory_location',['latitude'=>round((float)$data['latitude'],3),'longitude'=>round((float)$data['longitude'],3),'expires_at'=>time()+1800]);
        return response()->json(['ok'=>true])->header('Cache-Control','no-store');
    }
    public function show(string $slug,CustomerDirectoryService $service,Request $request){
        $data=$request->validate(['especialidad'=>['nullable','integer',\Illuminate\Validation\Rule::in(array_keys(config('specialties.catalog', [])))]]);
        $site=$this->site();$profile=$service->eligibleQuery()->where('slug',$slug)->firstOrFail();
        $card=$service->detail($profile);abort_unless($card,404);
        $selectedSpecialty=isset($data['especialidad']) ? (int)$data['especialidad'] : null;
        $candidate=$selectedSpecialty ? app(\App\Services\SpecialtyCatalog::class)->name($selectedSpecialty) : null;
        $specialtyMatch=$candidate && in_array($candidate,$card['specialties'],true) ? $candidate : null;
        $reviews=\App\Models\DirectoryReview::published()->where('customer_id',$profile->customer_id)
            ->with(['professional:id,name','replies'])->latest()->paginate(10, ['*'], 'resenas');
        return response()->view('vita.directory.show',compact('site','card','selectedSpecialty','specialtyMatch','reviews'))->header('Cache-Control','private, no-store');
    }
    public function sitemap(CustomerDirectoryService $service){
        $this->site();
        return response()->view('vita.directory.sitemap',['profiles'=>$service->eligibleQuery()->orderBy('id')->get(),'service'=>$service])->header('Content-Type','application/xml; charset=UTF-8');
    }
}
