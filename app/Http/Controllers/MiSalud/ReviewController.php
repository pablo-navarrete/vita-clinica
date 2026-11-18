<?php

namespace App\Http\Controllers\MiSalud;

use App\Http\Controllers\Controller;
use App\Models\DirectoryReview;
use App\Services\{CustomerDirectoryService, DirectoryReviewService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function show(string $slug, CustomerDirectoryService $directory, DirectoryReviewService $reviews)
    {
        $profile = $directory->eligibleQuery()->where('slug', $slug)->firstOrFail();
        $account = Auth::guard('mi_salud')->user();
        // Do not accept the local preview bypass for public contributions.
        abort_unless($account->email_verified_at, 403, 'Verifica tu correo para publicar una reseña.');
        return view('mi-salud.reviews', [
            'page'=>'reviews', 'account'=>$account, 'profile'=>$profile,
            'encounters'=>$reviews->eligibleEncounters($account, (int)$profile->customer_id)
                ->with('professional:id,name')->orderByDesc('ended_at')->get(['id','professional_id','ended_at']),
            'review'=>DirectoryReview::where('customer_id', $profile->customer_id)->where('portal_account_id', $account->id)->first(),
        ]);
    }

    public function store(Request $request, string $slug, CustomerDirectoryService $directory, DirectoryReviewService $reviews)
    {
        $data = $request->validate([
            'encounter_id'=>'required|integer', 'target'=>'required|in:clinic,professional',
            'rating'=>'required|integer|between:1,5', 'public_name'=>'required|string|max:60|not_regex:/^\s*$/u',
            'comment'=>'required|string|min:10|max:2000|not_regex:/^\s*$/u', 'consent'=>'required|accepted',
        ]);
        $profile = $directory->eligibleQuery()->where('slug', $slug)->firstOrFail();
        $reviews->publish(Auth::guard('mi_salud')->user(), (int)$profile->customer_id, $data);
        return redirect()->route('mi-salud.reviews.show', $slug)->with('status', 'Tu reseña ya está publicada en la vitrina.');
    }

    public function withdraw(Request $request, string $slug, CustomerDirectoryService $directory)
    {
        // Withdrawal remains available even after a clinic leaves the directory.
        $profile = \App\Models\CustomerDirectoryProfile::where('slug', $slug)->firstOrFail();
        DirectoryReview::where('customer_id', $profile->customer_id)
            ->where('portal_account_id', Auth::guard('mi_salud')->id())->whereNull('withdrawn_at')->firstOrFail()
            ->update(['withdrawn_at'=>now()]);
        return redirect()->route('mi-salud.panel')->with('status', 'Tu reseña dejó de mostrarse públicamente.');
    }
}
