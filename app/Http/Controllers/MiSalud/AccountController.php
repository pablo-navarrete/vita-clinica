<?php

namespace App\Http\Controllers\MiSalud;

use App\Http\Controllers\Controller;
use App\Models\PortalAccount;
use App\Services\MiSalud\PortalAccountService;
use App\Services\Identification\{CountryCatalog,DocumentTypeRegistry};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Hash,RateLimiter};
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    private const RECEIVED='Si corresponde, recibirás un correo con los pasos para continuar. Revisa también tu carpeta de spam. Puedes solicitar otro enlace si no llega.';
    public function __construct(private PortalAccountService $accounts) {}
    private function account(): PortalAccount { return Auth::guard('mi_salud')->user(); }
    private function identityRules(bool $required=true): array
    {
        return ['identification_country'=>'required|string|size:2','identification_type'=>'required|string|max:32',
            'identification_number'=>($required?'required':'nullable').'|string|max:128'];
    }
    private function form(string $page)
    {
        return view('mi-salud.page',['page'=>$page,'account'=>Auth::guard('mi_salud')->user(),
            'countries'=>app(CountryCatalog::class)->all(),'types'=>app(DocumentTypeRegistry::class)->available('CL')]);
    }
    public function home() { return redirect()->route(Auth::guard('mi_salud')->check()?'mi-salud.panel':'mi-salud.login'); }
    public function sessionStatus(Request $request)
    {
        $account = Auth::guard('mi_salud')->user()?->fresh();
        return response()->json(['authenticated'=>(bool)($account && $account->active
            && (int)$account->session_version === (int)$request->session()->get('portal_session_version'))]);
    }
    public function registerForm() { return $this->form('register'); }
    public function loginForm()
    {
        if (Auth::guard('mi_salud')->check()) {
            $intent=session('portal_booking_intent');
            if (is_string($intent) && \Illuminate\Support\Str::isUuid($intent)) return redirect()->route('mi-salud.booking.show',['intent'=>$intent]);
            return redirect()->route('mi-salud.panel');
        }
        return $this->form('login');
    }
    public function register(Request $request)
    {
        $request->merge(['email'=>mb_strtolower(trim((string)$request->input('email')))]);
        $data=$request->validate(['name'=>'required|string|max:255','email'=>'required|email|max:255',
            'password'=>['required','confirmed',Password::min(12)->letters()->numbers()]]+$this->identityRules());
        $this->accounts->register($data);
        return redirect()->route('mi-salud.login')->with('status',self::RECEIVED);
    }
    public function login(Request $request)
    {
        $data=$request->validate(['email'=>'required|email|max:255','password'=>'required|string|max:1024']);
        $email=mb_strtolower(trim($data['email'])); $key='mi-salud-login:'.hash('sha256',$email.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key,5)) throw ValidationException::withMessages(['email'=>'Demasiados intentos. Espera un momento antes de volver a intentar.']);
        $account=PortalAccount::where('email',$email)->first();
        $valid=Hash::check($data['password'],$account?->password ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$account || !$valid || !$account->active) {
            RateLimiter::hit($key,300); $this->accounts->audit(null,'login_failed');
            throw ValidationException::withMessages(['email'=>'El correo o la contraseña no son correctos.']);
        }
        $bookingIntent=$request->session()->get('portal_booking_intent');
        $reviewSlug=$request->session()->get('portal_review_slug');
        RateLimiter::clear($key); $request->session()->flush();
        Auth::guard('mi_salud')->login($account); $request->session()->regenerate();
        $request->session()->regenerateToken(); $request->session()->put('portal_session_version',$account->session_version);
        $this->accounts->audit($account->id,'login_succeeded');
        if (is_string($bookingIntent) && \Illuminate\Support\Str::isUuid($bookingIntent)) {
            $request->session()->put('portal_booking_intent',$bookingIntent);
            return redirect()->route('mi-salud.booking.show',['intent'=>$bookingIntent]);
        }
        if ($account->email_verified_at && is_string($reviewSlug) && preg_match('/\A[a-z0-9-]{1,255}\z/', $reviewSlug)) {
            return redirect()->route('mi-salud.reviews.show', ['slug'=>$reviewSlug]);
        }
        return redirect()->route($account->email_verified_at?'mi-salud.panel':'mi-salud.verify.notice');
    }
    public function logout(Request $request)
    {
        $this->accounts->audit($this->account()->id,'logout'); Auth::guard('mi_salud')->logout();
        $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('mi-salud.login');
    }
    public function verifyNotice() { return $this->form('verify'); }
    public function localPreview(Request $request)
    {
        abort_unless(\App\Http\Middleware\PortalAccess::localPreviewAllowed(),404);
        $request->session()->put('portal_local_preview_account',$this->account()->id);
        $this->accounts->audit($this->account()->id,'local_panel_preview');
        return redirect()->route('mi-salud.panel')->with('status','Vista de prueba local. Tu correo permanece sin verificar.');
    }
    public function resend()
    {
        $this->accounts->sendLink($this->account(),'verify');
        return back()->with('status',self::RECEIVED);
    }
    public function forgotForm() { return $this->form('forgot'); }
    public function forgot(Request $request)
    {
        $data=$request->validate(['email'=>'required|email|max:255']);
        $account=PortalAccount::where('email',mb_strtolower(trim($data['email'])))->where('active',true)->first();
        if ($account) $this->accounts->sendLink($account,'reset');
        return back()->with('status',self::RECEIVED);
    }
    public function tokenForm(string $purpose) { return $this->form($purpose==='verify'?'verify-token':'reset'); }
    public function token(Request $request, string $purpose)
    {
        $rules=['security_token'=>'required|string|size:64'];
        if($purpose==='reset') $rules['password']=['required','confirmed',Password::min(12)->letters()->numbers()];
        $validator=\Illuminate\Support\Facades\Validator::make($request->all(),$rules);
        if ($validator->fails()) {
            // Retain a syntactically valid bearer only in the no-store response, never in flash/session or URL.
            $bearer=$request->input('security_token');
            $bearer=is_string($bearer) && preg_match('/\A[a-f0-9]{64}\z/D',$bearer)?$bearer:'';
            $errors=(new \Illuminate\Support\ViewErrorBag)->put('default',$validator->errors());
            return response($this->form($purpose==='verify'?'verify-token':'reset')->with('errors',$errors)->with('securityToken',$bearer),422);
        }
        $data=$validator->validated();
        if (!$this->accounts->consume($purpose,$data['security_token'],$data['password']??null)) return back()->withErrors(['security_token'=>'El enlace no es válido o venció. Solicita uno nuevo.']);
        return redirect()->route('mi-salud.login')->with('status',$purpose==='verify'?'Correo verificado. Ya puedes ingresar a Mi Salud.':'Contraseña actualizada. Inicia sesión nuevamente.');
    }
    public function panel() {
        $filters=request()->validate(['year'=>'nullable|integer|min:1900|max:2100','month'=>'nullable|integer|min:1|max:12']);
        $year=(int)($filters['year'] ?? now()->year);
        $summary=app(\App\Services\MiSalud\PortalClinicalAccessService::class)->summary($this->account(),$year ?: now()->year,
            empty($filters['month']) ? null : (int)$filters['month']);
        if (request()->header('X-Mi-Salud-Summary')==='1') {
            return response()->view('mi-salud.attentions.summary',compact('summary'))
                ->header('X-Mi-Salud-Summary','1');
        }
        $reviews=\App\Models\DirectoryReview::where('portal_account_id',$this->account()->id)->published()
            ->join('customer_directory_profiles as directory','directory.customer_id','=','directory_reviews.customer_id')
            ->join('customers','customers.id','=','directory_reviews.customer_id')
            ->get(['directory.slug','customers.name','directory_reviews.rating']);
        return $this->form('panel')->with('myReviews',$reviews)->with('summary',$summary);
    }
    public function profile()
    {
        return $this->form('profile')->with('identification',$this->accounts->identifier($this->account()))
            ->with('sharing',app(\App\Services\MiSalud\PortalProfileSharingService::class)->profile($this->account()));
    }
    public function updateProfile(Request $request)
    {
        $data=$request->validate(['name'=>'required|string|max:255','current_password'=>'required|string|max:1024',
            'password'=>['nullable','confirmed',Password::min(12)->letters()->numbers()],
            'sharing_settings_present'=>'sometimes|in:1','share_basic_profile'=>'sometimes|boolean',
            'gender'=>'nullable|in:Masculino,Femenino,Otro','birth_date'=>'nullable|date|before_or_equal:today',
            'phone'=>'nullable|string|max:20','address'=>'nullable|string|max:255']+$this->identityRules(false));
        $this->accounts->profile($this->account(),$data);
        $request->session()->put('portal_session_version',$this->account()->fresh()->session_version);
        $request->session()->regenerate();
        return redirect()->route('mi-salud.profile')->with('status','Tu perfil fue actualizado.');
    }
}
