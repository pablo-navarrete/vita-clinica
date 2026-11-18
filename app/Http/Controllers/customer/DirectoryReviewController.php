<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\DirectoryReview;
use App\Services\DirectoryReviewService;
use App\Support\ClinicBranchContext;
use Illuminate\Http\Request;

class DirectoryReviewController extends Controller
{
    public function index(Request $request, DirectoryReviewService $service)
    {
        $user = $request->user(); $customer = (int)app('customer')->id;
        $branch = ClinicBranchContext::defaultBranch($user, $customer);
        abort_unless((int)$user->customer_id === $customer && (int)$user->status === 1 && $branch
            && $user->branches()->whereKey($branch->id)->wherePivot('status', true)->exists()
            && ($user->hasClinicRole('Administrador', $customer, (int)$branch->id) || $user->hasClinicRole('Doctor', $customer, (int)$branch->id)), 403);
        $isAdmin = $user->hasClinicRole('Administrador', $customer, (int)$branch->id);
        $reviews = DirectoryReview::published()->where('customer_id', $customer)
            ->whereHas('encounter', function ($query) use ($branch, $user, $isAdmin) {
                $query->where('clinic_branch_id', $branch->id);
                if (!$isAdmin) $query->where('professional_id', $user->id);
            })->with(['replies','professional:id,name','encounter:id,clinic_branch_id,professional_id'])
            ->latest()->paginate(15);
        return view('customer.directory.reviews', compact('reviews','service'));
    }

    public function reply(Request $request, string $customer, int $review, DirectoryReviewService $service)
    {
        $data = $request->validate(['body'=>'required|string|min:5|max:2000|not_regex:/^\s*$/u', 'consent'=>'required|accepted']);
        $entry = DirectoryReview::where('customer_id', app('customer')->id)->findOrFail($review);
        $service->reply($request->user(), $entry, $data['body']);
        return redirect()->route('customers.directory.reviews', ['customer'=>$customer])->with('success', 'Respuesta publicada en la vitrina.');
    }
}
