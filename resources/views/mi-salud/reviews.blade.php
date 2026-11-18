@extends('mi-salud.layout')
@section('content')
<section class="page-heading"><span class="eyebrow">OPINIONES</span><h1>Tu experiencia en {{ $profile->customer->name }}</h1><p>Comparte cómo fue la atención. Tu opinión será pública en la vitrina de VITA.</p></section>
<section class="card">
@if($review)
    @if($review->withdrawn_at)<p>Tu opinión fue retirada y ya no se muestra públicamente.</p>
    @else
        <h2>Tu opinión publicada</h2><p>{{ $review->rating }} / 5 estrellas · {{ $review->public_name }}</p><p>{{ $review->comment }}</p>
        <form method="POST" action="{{ route('mi-salud.reviews.withdraw', $profile->slug) }}">@csrf @method('DELETE')<button class="button" type="submit">Retirar mi opinión</button></form>
    @endif
@elseif($encounters->isEmpty())
    <h2>Todavía no puedes evaluar esta atención</h2><p>Necesitas una atención finalizada cuya reserva hayas realizado desde tu cuenta de Mi Salud.</p>
@else
    <form class="form-grid" method="POST" action="{{ route('mi-salud.reviews.store', $profile->slug) }}">@csrf
        <div class="field full"><label for="review-encounter">Atención que quieres evaluar</label><select id="review-encounter" name="encounter_id" required>@foreach($encounters as $encounter)<option value="{{ $encounter->id }}" @selected(old('encounter_id')==$encounter->id)>{{ $encounter->ended_at?->format('d/m/Y') }} · {{ $encounter->professional?->name ?? 'Clínica' }}</option>@endforeach</select></div>
        <div class="field full"><label for="review-target">¿A quién evalúas?</label><select id="review-target" name="target" required><option value="clinic" @selected(old('target')==='clinic')>A la clínica</option><option value="professional" @selected(old('target')==='professional')>Al profesional que me atendió</option></select></div>
        <fieldset class="field full review-stars"><legend>Puntuación de la atención</legend><div class="review-star-options">@for($star=5;$star>=1;$star--)<input id="rating-{{ $star }}" type="radio" name="rating" value="{{ $star }}" @checked(old('rating')==$star) required><label for="rating-{{ $star }}" title="{{ $star }} {{ $star===1 ? 'estrella' : 'estrellas' }}"><span aria-hidden="true">★</span><span class="review-sr-only">{{ $star }} {{ $star===1 ? 'estrella' : 'estrellas' }}</span></label>@endfor</div><p class="help">Selecciona de 1 a 5 estrellas.</p></fieldset>
        <div class="field full"><label for="review-name">Nombre público</label><input id="review-name" name="public_name" value="{{ old('public_name') }}" maxlength="60" required placeholder="Por ejemplo: Ana M."><p class="help">Puedes usar un alias. No mostraremos tu correo ni tu documento.</p></div>
        <div class="field full"><label for="review-comment">¿Cómo fue tu experiencia?</label><textarea id="review-comment" name="comment" rows="5" minlength="10" maxlength="2000" required>{{ old('comment') }}</textarea><p class="help">Comenta el trato, la puntualidad o la comunicación. Evita publicar diagnósticos, documentos, teléfonos o información privada de otras personas.</p></div>
        <label class="field full"><span><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> Autorizo publicar mi nombre público, puntuación y comentario, y recibir respuestas públicas de la clínica o del profesional.</span></label>
        <div class="full"><button class="button" type="submit">Publicar opinión</button></div>
    </form>
@endif
<p><a href="{{ route('vita.directory.show', $profile->slug) }}#opiniones">Ver opiniones en la vitrina</a></p>
</section>
@endsection
