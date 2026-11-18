<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalSignatureAsset;
use App\Services\Signatures\ProfessionalSignatureService;
use App\Services\Signatures\SignatureMethodRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfessionalSignatureController extends Controller
{
    public function index(Request $request, ProfessionalSignatureService $service, SignatureMethodRegistry $methods)
    {
        $user = $request->user();
        $customerId = (int) app('customer')->id;
        abort_unless($service->canManage($user, $customerId), 403);
        return view('customer.user.signature', [
            'user' => $user,
            'signatureProfile' => $service->profile($user, $customerId),
            'signatureMethods' => $methods->available(),
            'signatureSpecialties' => $user->specialties()->wherePivot('customer_id', $customerId)
                ->where('specialties.customer_id', $customerId)->get()->unique('id'),
        ]);
    }

    public function update(Request $request, ProfessionalSignatureService $service, SignatureMethodRegistry $methods)
    {
        $customerId = (int) app('customer')->id;
        abort_unless($service->canManage($request->user(), $customerId), 403);
        $imageRules = ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg', 'mimetypes:image/png,image/jpeg', 'max:2048', 'dimensions:min_width=32,min_height=16,max_width=2400,max_height=1600'];
        $data = $request->validate([
            'user_id' => ['prohibited'], 'customer_id' => ['prohibited'],
            'method' => ['required', Rule::in(array_keys($methods->available()))],
            'signature' => $imageRules, 'stamp' => $imageRules,
            'remove_signature' => ['sometimes', 'boolean'], 'remove_stamp' => ['sometimes', 'boolean'],
        ]);
        $service->save($request->user(), $customerId, $data);

        return redirect()->route('customers.signature.index', ['customer' => $request->route('customer')])
            ->with('success', 'Tu configuración de firma electrónica se guardó correctamente.');
    }

    public function image(Request $request, string $customer, string $uuid, ProfessionalSignatureService $service)
    {
        $customerId = (int) app('customer')->id;
        abort_unless($service->canManage($request->user(), $customerId), 403);
        $asset = ProfessionalSignatureAsset::where('customer_id', $customerId)
            ->where('user_id', $request->user()->id)->where('uuid', $uuid)->firstOrFail();
        // Only current previews are exposed. Old versions are retained for future document references.
        $profile = $service->profile($request->user(), $customerId);
        abort_unless($profile && in_array($asset->id, [$profile->signature_asset_id, $profile->stamp_asset_id]), 404);
        $disk = Storage::disk('professional_signatures');
        abort_unless($disk->exists($asset->path), 404);

        return response($disk->get($asset->path), 200, [
            'Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'inline; filename="visual.png"',
        ]);
    }
}
