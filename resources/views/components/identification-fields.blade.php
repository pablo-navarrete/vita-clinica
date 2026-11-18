@php($prefix = $prefix ?? '')
@php($kind = $kind ?? 'user')
@once
@php($identificationCountries = array_map(fn($code,$name)=>[$code,$name],array_keys(app(\App\Services\Identification\CountryCatalog::class)->all()),array_values(app(\App\Services\Identification\CountryCatalog::class)->all())))
<script>
window.VitaIdentification = {
    countries: @json($identificationCountries),
    requests: new WeakMap(),
    isLoading(form) { return Object.values(this.requests.get(form)||{}).some(Boolean); },
    fold(value) { return String(value).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase(); },
    defaults(kind='user', prefix='') {
        const values = {identification_enabled:true,identification_country:'CL',identification_type:kind==='customer'?'tax_id':'national_id',identification_holder:kind==='customer'?'legal_entity':'natural_person',identification_number:'',identification_expected_primary:'',identification_legacy:'',identification_display:''};
        return Object.fromEntries(Object.entries(values).map(([key,value])=>[prefix+key,value]));
    },
    validateNumber(form, prefix='') {
        if(!form[prefix+'identification_enabled'] || form[prefix+'identification_country']!=='CL' || !['national_id','tax_id'].includes(form[prefix+'identification_type']))return '';
        const number=String(form[prefix+'identification_number'] ?? '').replace(/^ +| +$/g,'');
        if(!/^(?:[0-9]{1,8}-?[0-9kK]|[0-9]{1,3}(?:\.[0-9]{3}){1,2}-[0-9kK])$/.test(number))return 'Ingresa un RUT chileno con un formato válido.';
        const compact=number.replace(/[.-]/g,'').toUpperCase(), body=compact.slice(0,-1);
        if(!/^[1-9][0-9]{0,7}$/.test(body))return 'El cuerpo del RUT debe tener entre 1 y 8 dígitos, sin ceros iniciales.';
        let sum=0,weight=2;
        for(let i=body.length-1;i>=0;i--){sum+=Number(body[i])*weight;weight=weight===7?2:weight+1;}
        const remainder=11-(sum%11), expected=remainder===11?'0':remainder===10?'K':String(remainder);
        if(compact.slice(-1)!==expected)return 'El dígito verificador del RUT no es válido.';
        form[prefix+'identification_number']=body.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+'-'+expected;
        return '';
    },
    countrySelect(getForm, prefix='') {
        const catalog=this;
        return {
            opened:false, query:'', activeIndex:0,
            get value() { return getForm()[prefix+'identification_country'] || ''; },
            get disabled() { return !getForm()[prefix+'identification_enabled']; },
            get options() { const query=catalog.fold(this.query).trim(); return catalog.countries.filter(country=>catalog.fold(country.join(' ')).includes(query)); },
            get label() { return catalog.countries.find(country=>country[0]===this.value)?.[1] || 'Selecciona el país emisor'; },
            open() {
                if(this.disabled)return;
                this.query=''; this.opened=true;
                this.activeIndex=Math.max(0,this.options.findIndex(country=>country[0]===this.value));
                this.$nextTick(()=>{this.$refs.countrySearch.focus();this.scrollActive();});
            },
            close(restoreFocus=false) { this.opened=false; if(restoreFocus)this.$refs.countryTrigger.focus(); },
            move(step) { if(!this.opened){this.open();return;} this.activeIndex=Math.max(0,Math.min(this.options.length-1,this.activeIndex+step)); this.$nextTick(()=>this.scrollActive()); },
            scrollActive() { this.$refs.countryResults.querySelectorAll('[role=option]')[this.activeIndex]?.scrollIntoView({block:'nearest'}); },
            choose(code) { if(this.disabled)return; getForm()[prefix+'identification_country']=code; this.close(true); },
            chooseActive() { const country=this.options[this.activeIndex]; if(country)this.choose(country[0]); }
        };
    },
    async load(url,form,prefix='') {
        const loads=this.requests.get(form)||{}, token=Symbol(); loads[prefix]=token;this.requests.set(form,loads);
        try {
            const response=await fetch(url,{headers:{Accept:'application/json'},cache:'no-store'});
            if(!response.ok)throw new Error('No se pudo consultar la identificación. Recarga antes de guardar.');
            const values=await response.json();
            if(loads[prefix]!==token)return;
            Object.entries(values).filter(([key])=>key.startsWith('identification_')).forEach(([key,value])=>form[prefix+key]=value);
        } finally { if(loads[prefix]===token)loads[prefix]=false; }
    }
};
</script>
<style>
.vita-identification {grid-column:1 / -1;min-width:0;border:1px solid #e5e7eb;border-radius:.75rem;padding:1rem;background:#fff;}
.vita-identification label {display:block;margin-bottom:.4rem;font-size:.875rem;font-weight:500;}
.vita-identification :is(input:not([type=checkbox]),select) {box-sizing:border-box;width:100%;min-width:0;min-height:42px;border:1px solid #d1d5db;border-radius:.5rem;padding:.5rem .75rem;background:#fff;}
.vita-identification :is(input,select):focus-visible {outline:2px solid var(--id-accent,#4f46e5);outline-offset:2px;}
.vita-identification :is(input,select):disabled {background:#f3f4f6;color:#6b7280;}
.vita-identification-grid {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;}

.vita-country-select {position:relative;min-width:0;}
.vita-identification [x-cloak] {display:none!important;}
.vita-country-trigger {display:flex;align-items:center;justify-content:space-between;gap:.75rem;width:100%;min-height:42px;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:.5rem;background:#fff;text-align:left;font-size:.875rem;}
.vita-country-trigger span {overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.vita-country-trigger svg {flex-shrink:0;}
.vita-country-trigger:disabled {background:#f3f4f6;color:#6b7280;cursor:not-allowed;}
.vita-country-trigger:focus-visible {outline:2px solid var(--id-accent,#4f46e5);outline-offset:2px;}
.vita-country-dropdown {position:absolute;top:calc(100% + .35rem);left:0;right:0;z-index:60;border:1px solid #d1d5db;border-radius:.65rem;background:#fff;box-shadow:0 10px 25px #0000001a;overflow:hidden;}
.vita-country-search {padding:.5rem;border-bottom:1px solid #e5e7eb;}
.vita-country-options {max-height:210px;overflow-y:auto;overscroll-behavior:contain;padding:.25rem;}
.vita-country-options button {display:block;width:100%;padding:.6rem .75rem;border:0;border-radius:.35rem;background:#fff;text-align:left;font-size:.875rem;color:#374151;}
.vita-country-options button.is-active {background:#f3f4f6;}
.vita-country-options button.is-selected {color:var(--id-accent,#4f46e5);font-weight:600;}
@media(max-width:640px){.vita-identification-grid{grid-template-columns:1fr;}}
</style>
@endonce
<section class="vita-identification col-span-full" x-data="{numberError:'',numberForm:null,numberContext:''}" x-effect="const context=[form['{{ $prefix }}identification_country'],form['{{ $prefix }}identification_type'],form['{{ $prefix }}identification_enabled'],form['{{ $prefix }}identification_expected_primary']].join('|'); if(numberForm!==form || numberContext!==context){numberForm=form;numberContext=context;numberError='';}" style="--id-accent:{{ themeColor(600) }}" aria-label="{{ $kind==='customer'?'Identificación tributaria':'Identificación personal' }}">
    <h4 class="mb-3 font-semibold">{{ $kind==='customer'?'Identificación tributaria':'Identificación personal' }}</h4>
    <p class="mb-3 text-sm text-gray-500" x-show="form['{{ $prefix }}identification_legacy']" x-text="'Documento histórico sin clasificar: ' + (form['{{ $prefix }}identification_legacy'] || '')"></p>
    <label class="mb-3" x-show="isEdit">
        <input type="checkbox" name="{{ $prefix }}identification_enabled" value="1" x-model="form['{{ $prefix }}identification_enabled']"> Actualizar documento principal
    </label>
    <input type="hidden" name="{{ $prefix }}identification_expected_primary" :value="form['{{ $prefix }}identification_expected_primary'] || ''">
    <div class="vita-identification-grid">
        <div>
            @include('components.identification-country')
        </div>
        <div>
            @if($kind==='customer')
            <label for="{{ $prefix }}identification_holder">Tipo de titular</label>
            <select class="form-select" id="{{ $prefix }}identification_holder" name="{{ $prefix }}identification_holder" x-model="form['{{ $prefix }}identification_holder']" :disabled="!form['{{ $prefix }}identification_enabled']"><option value="">Selecciona el titular</option><option value="natural_person">Persona natural</option><option value="legal_entity">Persona jurídica</option></select>
            @endif
            <label for="{{ $prefix }}identification_type" class="mt-2">Tipo de documento</label>
            <select class="form-select" id="{{ $prefix }}identification_type" name="{{ $prefix }}identification_type" x-model="form['{{ $prefix }}identification_type']" :disabled="!form['{{ $prefix }}identification_enabled']">
            @if($kind==='customer')<option value="tax_id" x-text="form['{{ $prefix }}identification_country']==='CL'?'RUT tributario':'Identificador tributario'"></option>
            @else<option value="national_id" x-text="form['{{ $prefix }}identification_country']==='CL'?'RUT / RUN':'Documento nacional'"></option><option value="passport">Pasaporte</option>@if($kind==='patient')<option value="tax_id">Identificador tributario personal</option>@endif
            @endif
            </select>
            <label for="{{ $prefix }}identification_number" class="mt-3">Número de identificación</label>
            <input class="form-control" id="{{ $prefix }}identification_number" name="{{ $prefix }}identification_number" type="text" maxlength="128" x-model="form['{{ $prefix }}identification_number']" @blur="numberError=VitaIdentification.validateNumber(form,'{{ $prefix }}')" @input="numberError=''; delete errors['{{ $prefix }}identification_number']" :aria-invalid="Boolean(numberError || errors['{{ $prefix }}identification_number'])" :aria-describedby="numberError ? '{{ $prefix }}identification_number_error' : null" :style="numberError ? 'border-color:#dc2626' : ''" :disabled="!form['{{ $prefix }}identification_enabled']" autocomplete="off" spellcheck="false" :placeholder="form['{{ $prefix }}identification_country']==='CL' && form['{{ $prefix }}identification_type']!=='passport'?'12.345.678-5':'Número tal como aparece en el documento'">
        </div>
    </div>
    <p class="mt-3 text-xs text-gray-500">El país emisor no indica nacionalidad ni residencia. Validar el documento no verifica la identidad de su titular. Los documentos anteriores se conservan.</p>
    <p id="{{ $prefix }}identification_number_error" x-show="numberError" x-cloak role="alert" class="mt-2 text-sm text-red-600" x-text="numberError"></p>
    <template x-if="!numberError && errors['{{ $prefix }}identification_number']"><p role="alert" class="mt-2 text-sm text-red-600" x-text="errors['{{ $prefix }}identification_number'][0]"></p></template>
</section>
