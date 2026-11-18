<section class="card attention-summary" aria-labelledby="attention-summary-title">
    @php($labels=['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'])
    <div class="attention-summary-heading"><div><h2 id="attention-summary-title">Tus atenciones por mes</h2><p class="help">Atenciones firmadas de los centros que habilitaron tu acceso. Cambia el año o el mes para actualizar el resumen automáticamente.</p></div>
        <form method="GET" action="{{ route('mi-salud.panel') }}" data-attention-filters><label for="attention-year">Año</label><select id="attention-year" name="year">@foreach($summary['years'] as $year)<option value="{{ $year }}" @selected($year===$summary['year'])>{{ $year }}</option>@endforeach</select><label for="attention-month">Mes</label><select id="attention-month" name="month"><option value="">Todos los meses</option>@foreach($labels as $index=>$label)<option value="{{ $index+1 }}" @selected($summary['month']===$index+1)>{{ $label }}</option>@endforeach</select><noscript><button class="button small" type="submit">Ver</button></noscript></form>
    </div>
    <div data-attention-results>
    <p aria-live="polite"><strong>{{ $summary['total'] }}</strong> {{ $summary['total']===1 ? 'atención en' : 'atenciones en' }} @if($summary['month']){{ $labels[$summary['month']-1] }} de @endif{{ $summary['year'] }}</p>
    @php($labels=['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'])
    <div class="attention-chart-container"><canvas role="img" aria-label="Atenciones por mes en {{ $summary['year'] }}" data-attention-chart="{{ json_encode(['labels'=>$labels,'values'=>array_values($summary['months']),'month'=>$summary['month']]) }}">Atenciones realizadas por mes.</canvas></div>
    <table class="portal-visually-hidden"><caption>Atenciones por mes en {{ $summary['year'] }}</caption><thead><tr><th scope="col">Mes</th><th scope="col">Atenciones</th></tr></thead><tbody>@foreach($summary['months'] as $month=>$count)<tr><th scope="row">{{ $labels[$month-1] }}</th><td>{{ $count }}</td></tr>@endforeach</tbody></table>
    @if(!$summary['total'])<p class="help">No hay atenciones firmadas disponibles en el período seleccionado.</p>@endif
    <div class="attention-rankings">
        @foreach(['clinics'=>'Centros donde más te atendiste','professionals'=>'Profesionales con más atenciones'] as $key=>$heading)
            <section><h3>{{ $heading }}</h3>
            @if(count($summary[$key]))<div class="attention-chart-container attention-ranking-chart"><canvas role="img" aria-label="{{ $heading }}" data-attention-chart="{{ json_encode(['labels'=>array_column($summary[$key],'name'),'values'=>array_column($summary[$key],'count'),'ranking'=>true]) }}">{{ $heading }}</canvas></div>@endif
            <ol>@forelse($summary[$key] as $item)<li><span>{{ $item['name'] }}</span><strong>{{ $item['count'] }} {{ $item['count']===1?'atención':'atenciones' }}</strong></li>@empty<li>Sin atenciones disponibles en este período.</li>@endforelse</ol></section>
        @endforeach
    </div>
    </div>
</section>
