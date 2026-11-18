<?php
namespace App\Services;
use App\Models\{Customer,CustomerDirectoryProfile,Logo,WebsiteBanner,User};
use App\Policies\CustomerDirectoryPolicy;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class CustomerDirectoryService
{
    public function eligibleQuery(){
        return CustomerDirectoryProfile::where('publication_enabled',true)->whereNotNull('authorized_at')->whereNotNull('authorized_by')
            ->where('authorization_version','directory-v1')->whereNull('suspended_at')
            ->whereHas('customer',fn($q)=>$q->where('status',1)->whereRaw("domain REGEXP '^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?$'")->whereHas('publicHome')
                ->whereDoesntHave('websiteMaintenance',fn($m)=>$m->where('enabled',true)))
            ->with(['customer:id,name,domain','customer.directoryLogos','logo','banner'])
            ->withCount('publishedReviews')->withAvg('publishedReviews', 'rating');
    }
    public function websiteUrl(Customer $customer): ?string {
        if(!preg_match('/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/i',(string)$customer->domain))return null;
        return route('customer.website.home',['customer'=>$customer->domain]);
    }
    public function filterBySpecialty($profiles, int $catalogId): void
    {
        $profiles->whereIn('customer_directory_profiles.customer_id', app(SpecialtyCatalog::class)->offeredQuery()
            ->where('catalog_id', $catalogId)->select('specialties.customer_id'));
    }

    public function specialtyOptions()
    {
        $catalog = app(SpecialtyCatalog::class);
        $ids = $catalog->offeredQuery()
            ->whereIn('specialties.customer_id', $this->eligibleQuery()->select('customer_directory_profiles.customer_id')->toBase())
            ->select('catalog_id')->distinct()->pluck('catalog_id');
        return $ids->map(fn ($id) => ['id'=>(int)$id,'name'=>$catalog->name((int)$id)])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }
    public function publicImage(?string $path,string $prefix): ?string {
        if(!$path||!str_starts_with($path,$prefix)||str_contains($path,'..')||str_contains($path,'\\')||!preg_match('/\.(png|jpe?g|webp|gif)\z/i',$path))return null;
        if(!Storage::disk('public')->exists($path))return null;
        $info=@getimagesize(Storage::disk('public')->path($path));
        return $info && in_array($info['mime']??'',['image/png','image/jpeg','image/webp','image/gif'],true) ? asset('storage/'.$path) : null;
    }
    public function card(CustomerDirectoryProfile $p): ?array {
        if(!$p->customer||!($url=$this->websiteUrl($p->customer)))return null;
        $id=$p->customer_id;
        $logoUrl=null;
        $candidates=collect();
        if($p->logo && $p->logo->customer_id==$id && $p->logo->status)$candidates->push($p->logo);
        foreach($p->customer->directoryLogos as $logo)$candidates->push($logo);
        foreach($candidates->unique('id') as $logo){
            if($logo->customer_id==$id && ($logoUrl=$this->publicImage($logo->url,"logos/customer_{$id}/")))break;
        }
        return ['name'=>$p->customer->name,'slug'=>$p->slug,'description'=>$p->description,
            'review_count'=>(int)$p->published_reviews_count,'rating'=>$p->published_reviews_avg_rating,
            'commune'=>$p->commune,'city'=>$p->city,'region'=>$p->region,'address'=>$p->public_address,'website'=>$url,'distance_km'=>$p->getAttribute('distance_km'),
            'logo'=>$logoUrl,'brand_display'=>$p->brand_display ?? 'both',
            'cover'=>match($p->cover_source ?? 'banner'){
                'uploaded'=>$this->publicImage($p->cover_path,"directory/customer_{$id}/"),
                'banner'=>$p->banner && $p->banner->customer_id==$id && $p->banner->status ? $this->publicImage($p->banner->image_path,"website-banners/customer_{$id}/") : null,
                default=>null,
            }];
    }
    public function detail(CustomerDirectoryProfile $profile): ?array
    {
        $card = $this->card($profile);
        if (!$card) return null;
        $id = (int) $profile->customer_id;
        $card['specialties'] = app(SpecialtyCatalog::class)->offered($id)->pluck('name')->all();
        $card['branches'] = $profile->customer->branches()->where('status', true)
            ->where('directory_publication_enabled', true)->orderBy('name')
            ->get(['name','address','directory_commune','directory_region'])->map(function ($branch) {
                $address = trim((string) $branch->address);
                $map = mb_strlen($address) >= 3 && preg_match('/[\pL\pN]/u', $address)
                    && !preg_match('/[\x00-\x1F]|:\/\//', $address)
                    ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(implode(', ', array_filter([$address,$branch->directory_commune,$branch->directory_region]))) : null;
                return ['name'=>$branch->name,'address'=>$address,'commune'=>$branch->directory_commune,
                    'region'=>$branch->directory_region,'map'=>$map];
            })->all();
        $card['contact'] = [];
        if ($profile->show_public_contact) {
            $contact = \App\Models\WebsiteTopbar::where('customer_id', $id)->where('enabled', true)->first(['phone','email']);
            $phone = trim((string) $contact?->phone);
            $dial = preg_replace('/[^0-9+]/', '', $phone);
            if (preg_match('/^\+?[0-9]{6,15}$/', $dial)) $card['contact']['phone'] = ['label'=>$phone,'url'=>'tel:'.$dial];
            $email = trim((string) $contact?->email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) $card['contact']['email'] = ['label'=>$email,'url'=>'mailto:'.$email];
        }
        $card['socials'] = [];
        if ($profile->show_public_social_links) {
            $settings = \App\Models\WebsiteSocialSetting::where('customer_id', $id)->first(['social_links']);
            $networks = \App\Models\WebsiteSocialSetting::networks();
            foreach ($settings?->social_links ?? [] as $link) {
                if (!is_array($link)) continue;
                $network = $link['network'] ?? null;
                $url = $link['url'] ?? null;
                if (!is_string($network) || !isset($networks[$network]) || !is_string($url)
                    || !filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x20]/', $url)) continue;
                $parts = parse_url($url);
                if (!in_array(strtolower($parts['scheme'] ?? ''), ['http','https'], true)
                    || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) continue;
                $card['socials'][$network] = $networks[$network] + ['url'=>$url];
            }
        }
        return $card;
    }

    public function save(User $actor,Customer $customer,array $data): void {
        abort_unless(app(CustomerDirectoryPolicy::class)->manage($actor,$customer->id),403);
        DB::transaction(function()use($actor,$customer,$data){
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $p=CustomerDirectoryProfile::where('customer_id',$customer->id)->lockForUpdate()->first();
            if((int)($p->revision??0)!==(int)$data['revision'])throw ValidationException::withMessages(['revision'=>'La configuración cambió. Recarga antes de guardar.']);
            $previousAuthorizations = $p?->only(['show_public_contact','show_public_social_links']);
            $branchChanges = [];
            if (array_key_exists('branches', $data)) {
                $submitted = collect($data['branches']);
                $ownedBranches = $customer->branches()->where('status', true)
                    ->whereIn('id', $submitted->pluck('id'))->lockForUpdate()->get()->keyBy('id');
                if ($submitted->pluck('id')->unique()->count() !== $submitted->count()
                    || $ownedBranches->count() !== $submitted->count()) {
                    throw ValidationException::withMessages(['branches'=>'Selecciona únicamente sedes activas de tu clínica.']);
                }
                foreach ($submitted as $entry) {
                    $branch = $ownedBranches->get($entry['id']);
                    $before = $branch->only(['directory_publication_enabled','directory_commune','directory_region']);
                    $branch->forceFill([
                        'directory_publication_enabled'=>(bool)$entry['publication_enabled'],
                        'directory_commune'=>$entry['commune'] ?? null,
                        'directory_region'=>$entry['region'] ?? null,
                    ]);
                    if ($branch->isDirty()) {
                        $branch->save();
                        $branchChanges[] = ['branch_id'=>$branch->id,'before'=>$before,
                            'after'=>$branch->only(['directory_publication_enabled','directory_commune','directory_region'])];
                    }
                }
            }
            foreach(['logo_id'=>Logo::class,'website_banner_id'=>WebsiteBanner::class] as $key=>$model){
                if(!empty($data[$key])&&!$model::where('customer_id',$customer->id)->where('status',1)->whereKey($data[$key])->exists())throw ValidationException::withMessages([$key=>'Selecciona un recurso público activo de tu clínica.']);
            }
            $enabled=(bool)$data['publication_enabled'];
            if($enabled&&empty($data['consent']))throw ValidationException::withMessages(['consent'=>'Debes autorizar expresamente la publicación.']);
            if($enabled&&(!$this->websiteUrl($customer)||!$customer->publicHome()->exists()))throw ValidationException::withMessages(['publication_enabled'=>'Configura primero la página de inicio pública de tu clínica.']);
            $p??=new CustomerDirectoryProfile(['customer_id'=>$customer->id,'slug'=>Str::slug($customer->name).'-'.Str::lower(Str::random(10))]);
            $p->fill(collect($data)->only(['description','logo_id','website_banner_id','public_address','city','region','brand_display','cover_source','cover_path','latitude','longitude'])->all());
            $p->fill(collect($data)->only(['commune','show_public_contact','show_public_social_links'])->all());
            if($p->cover_path&&!str_starts_with($p->cover_path,'directory/customer_'.$customer->id.'/'))throw ValidationException::withMessages(['cover'=>'Portada no válida para esta clínica.']);
            $p->publication_enabled=$enabled;
            if($enabled){$p->authorized_by=$actor->id;$p->authorized_at=now();$p->authorization_version='directory-v1';$p->withdrawn_at=null;}
            elseif($p->getOriginal('publication_enabled'))$p->withdrawn_at=now();
            $p->revision=(int)($p->getOriginal('revision')??0)+1;$p->save();
            DB::table('customer_directory_events')->insert(['customer_id'=>$customer->id,'actor_id'=>$actor->id,'action'=>$enabled?'publication_authorized':'profile_saved_unpublished','details'=>json_encode(['revision'=>$p->revision,'authorization_version'=>$p->authorization_version,'public_content'=>$p->only(['description','logo_id','website_banner_id','public_address','commune','city','region','brand_display','cover_source','cover_path','latitude','longitude','show_public_contact','show_public_social_links']),'previous_authorizations'=>$previousAuthorizations,'branch_changes'=>$branchChanges,'consent_text'=>'Autorizo la presentación, ubicación opcional, imágenes y sedes seleccionadas, y los datos de contacto y redes públicas expresamente habilitados, en el directorio público y su indexación por buscadores.']),'created_at'=>now()]);
        });
    }
}
