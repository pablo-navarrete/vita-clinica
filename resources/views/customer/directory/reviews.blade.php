@extends('layouts.menu-customer')
@section('header')<h2 class="text-xl font-semibold text-gray-800">Opiniones de pacientes</h2>@endsection
@section('content')
<div class="mx-auto max-w-4xl space-y-5 p-6">
    <p class="text-sm text-gray-600">Opiniones de la sede seleccionada. Los profesionales pueden responder las opiniones de sus propias atenciones.</p>
    @if(session('success'))<p class="rounded-lg bg-green-50 p-4" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="rounded-lg bg-red-50 p-4" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @forelse($reviews as $review)
        <article class="rounded-xl border border-gray-200 bg-white p-6">
            <h3 class="font-semibold">{{ $review->public_name }} <span class="text-amber-600">{{ str_repeat('★',$review->rating) }}{{ str_repeat('☆',5-$review->rating) }}</span></h3>
            <p class="mt-1 text-sm text-gray-500">{{ $review->created_at->format('d/m/Y') }} · {{ $review->professional_id ? 'Profesional: '.($review->professional?->name ?? 'Profesional') : 'Clínica' }}</p>
            <p class="mt-4 whitespace-pre-line break-words">{{ $review->comment }}</p>
            @foreach($review->replies as $reply)<div class="mt-4 rounded-lg bg-blue-50 p-4"><strong>{{ $reply->author_name }}</strong><p class="whitespace-pre-line break-words">{{ $reply->body }}</p></div>@endforeach
            @if($service->canReply(auth()->user(), $review) && !$review->replies->contains('user_id', auth()->id()))
                <form class="mt-5 space-y-3" method="POST" action="{{ route('customers.directory.reviews.reply', ['customer'=>request()->route('customer'),'review'=>$review->id]) }}">@csrf
                    <label class="block text-sm font-medium" for="review-reply-{{ $review->id }}">Responder públicamente</label>
                    <textarea id="review-reply-{{ $review->id }}" name="body" rows="3" minlength="5" maxlength="2000" required class="w-full rounded-lg border-gray-300" placeholder="Agradece o responde sobre la experiencia de atención."></textarea>
                    <label class="block text-sm"><input type="checkbox" name="consent" value="1" required> Autorizo publicar esta respuesta y mi identidad profesional cuando corresponda. No incluyo información clínica ni datos privados del paciente.</label>
                    <button class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white" type="submit">Publicar respuesta</button>
                </form>
            @endif
        </article>
    @empty<p class="rounded-xl bg-white p-6">Todavía no hay opiniones para mostrar.</p>@endforelse
    {{ $reviews->links() }}
</div>
@endsection
