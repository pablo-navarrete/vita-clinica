<section class="clinic-section clinic-reviews" id="opiniones" aria-labelledby="clinic-reviews-title">
    <div class="clinic-reviews-heading"><div><h2 id="clinic-reviews-title">Opiniones sobre la atención</h2>@include('vita.directory.rating')</div>
        <a class="directory-button" href="{{ route('mi-salud.reviews.show', $card['slug']) }}">Escribir una opinión</a>
    </div>
    <p>Opiniones de pacientes con una atención finalizada y una reserva realizada desde Mi Salud.</p>
    @forelse($reviews as $review)
        <article class="clinic-review">
            <div class="clinic-review-heading"><strong>{{ $review->public_name }}</strong><time datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->format('d/m/Y') }}</time></div>
            <div class="directory-rating-stars" aria-label="{{ $review->rating }} de 5 estrellas">@for($star=1;$star<=5;$star++)<i class="{{ $star <= $review->rating ? 'fa-solid' : 'fa-regular' }} fa-star" aria-hidden="true"></i>@endfor</div>
            <p class="clinic-review-target">{{ $review->professional_id ? 'Atención del profesional: '.($review->professional?->name ?? 'Profesional') : 'Atención de la clínica' }}</p>
            <p class="clinic-review-body">{{ $review->comment }}</p>
            @foreach($review->replies as $reply)
                <div class="clinic-review-reply"><strong>{{ $reply->author_name }}</strong> <span>{{ $reply->author_role==='clinic' ? 'Respuesta de la clínica' : 'Respuesta del profesional' }}</span><p class="clinic-review-body">{{ $reply->body }}</p></div>
            @endforeach
        </article>
    @empty
        <p>Aún no hay opiniones. Comparte cómo fue tu experiencia de atención.</p>
    @endforelse
    @if($reviews->hasPages())<nav class="directory-pages" aria-label="Páginas de opiniones">@if($reviews->previousPageUrl())<a href="{{ $reviews->previousPageUrl() }}#opiniones">Anteriores</a>@endif <span aria-current="page">{{ $reviews->currentPage() }}</span> @if($reviews->nextPageUrl())<a href="{{ $reviews->nextPageUrl() }}#opiniones">Siguientes</a>@endif</nav>@endif
</section>
