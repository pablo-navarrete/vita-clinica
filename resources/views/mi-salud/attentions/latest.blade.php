<section class="card history-latest" aria-labelledby="history-latest-title">
    <div class="history-latest-heading"><div><h2 id="history-latest-title">Tus últimas atenciones</h2><p class="help">Las tres más recientes, sin filtrar por año o mes. Solo aparecen atenciones firmadas con acceso habilitado.</p></div><a href="{{ route('mi-salud.attentions.index') }}">Ver todas</a></div>
    <div class="history-latest-grid">
    @forelse($summary['latest'] as $attention)
        <article class="history-latest-item">
            <time datetime="{{ $attention['date'] }}">{{ \Carbon\Carbon::parse($attention['date'])->timezone(config('app.timezone'))->format('d/m/Y · H:i') }}</time>
            <h3>{{ $attention['specialty'] }}</h3>
            <p><strong>{{ $attention['clinic'] }}</strong></p><p>{{ $attention['professional_name'] }}</p>
            <a class="button small" href="{{ route('mi-salud.attentions.encounter',['grant'=>$attention['grant_id'],'encounter'=>$attention['encounter_id']]) }}">Ver atención</a>
        </article>
    @empty
        <p class="help">Todavía no tienes atenciones firmadas disponibles. Cuando una clínica habilite tu acceso, podrás consultarlas aquí.</p>
    @endforelse
    </div>
</section>
