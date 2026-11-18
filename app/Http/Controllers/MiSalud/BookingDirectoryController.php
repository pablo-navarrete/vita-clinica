<?php

namespace App\Http\Controllers\MiSalud;

use App\Http\Controllers\Controller;
use App\Models\WebsiteNavigationButton;
use App\Services\CustomerDirectoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BookingDirectoryController extends Controller
{
    public function index(Request $request, CustomerDirectoryService $directory)
    {
        $filters=$request->validate(['buscar'=>'nullable|string|max:100','page'=>'nullable|integer|min:1|max:10000','cercanas'=>'nullable|in:1',
            'especialidad'=>['nullable','integer',Rule::in(array_keys(config('specialties.catalog',[])))]]);
        $search=trim($filters['buscar']??'');
        $selectedSpecialty=isset($filters['especialidad']) ? (int)$filters['especialidad'] : null;
        $query=$directory->eligibleQuery()->whereIn('customer_directory_profiles.customer_id',
            WebsiteNavigationButton::where('public_booking_enabled',true)->select('customer_id'));
        if ($selectedSpecialty) $directory->filterBySpecialty($query,$selectedSpecialty);
        if ($search!=='') {
            $term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';
            $query->where(fn($q)=>$q->whereHas('customer',fn($c)=>$c->where('name','like',$term))
                ->orWhere('commune','like',$term)->orWhere('city','like',$term)->orWhere('region','like',$term));
        }
        $nearby=false;$locationExpired=false;
        if ($request->boolean('cercanas')) {
            $location=$request->session()->get('portal_booking_location');
            if ($location && $location['expires_at']>time()) {
                $nearby=true;
                $query->select('customer_directory_profiles.*')->selectRaw(
                    '6371 * ACOS(LEAST(1,GREATEST(-1,SIN(RADIANS(?))*SIN(RADIANS(latitude))+COS(RADIANS(?))*COS(RADIANS(latitude))*COS(RADIANS(longitude)-RADIANS(?))))) AS distance_km',
                    [$location['latitude'],$location['latitude'],$location['longitude']])
                    ->whereNotNull('latitude')->whereNotNull('longitude')->orderBy('distance_km');
            } else {$request->session()->forget('portal_booking_location');$locationExpired=true;}
        }
        $profiles=$query->orderBy('customer_directory_profiles.id')->paginate(12)->withQueryString();
        return view('mi-salud.booking-directory',['page'=>'booking','account'=>Auth::guard('mi_salud')->user(),
            'profiles'=>$profiles,'directory'=>$directory,'search'=>$search,'selectedSpecialty'=>$selectedSpecialty,
            'nearby'=>$nearby,'locationExpired'=>$locationExpired,
            'specialtyOptions'=>collect(config('specialties.catalog',[]))->map(fn($name,$id)=>['id'=>(int)$id,'name'=>$name])
                ->sortBy('name',SORT_NATURAL|SORT_FLAG_CASE)->values()]);
    }

    public function location(Request $request)
    {
        $data=$request->validate(['latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180']);
        $request->session()->put('portal_booking_location',['latitude'=>round((float)$data['latitude'],3),
            'longitude'=>round((float)$data['longitude'],3),'expires_at'=>time()+1800]);
        return response()->json(['ok'=>true]);
    }
}
