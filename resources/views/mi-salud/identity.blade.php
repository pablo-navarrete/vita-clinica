<div class="field"><label for="identification-country">País emisor del documento</label>
<select id="identification-country" name="identification_country" data-country-select required>
@foreach($countries as $code=>$country)<option value="{{ $code }}" @selected(old('identification_country',$identification['country']??'CL')===$code)>{{ $country }} ({{ $code }})</option>@endforeach
</select></div>
<div class="field"><label for="identification-type">Tipo de documento</label>
@php
    $portalDocumentTypes = [];
    foreach (array_keys($countries) as $countryCode) {
        $portalDocumentTypes[$countryCode] = array_map(fn($definition) => ['value'=>$definition->type->value,'label'=>$definition->label], app(\App\Services\Identification\DocumentTypeRegistry::class)->available($countryCode));
    }
@endphp
<select id="identification-type" name="identification_type" data-document-types="{{ json_encode($portalDocumentTypes) }}" required>
@foreach($types as $type)<option value="{{ $type->type->value }}" @selected(old('identification_type',$identification['type']??'national_id')===$type->type->value)>{{ $type->type->value==='national_id'?'Documento nacional / RUT':($type->type->value==='passport'?'Pasaporte':'Identificador tributario personal') }}</option>@endforeach
</select></div>
<div class="field full"><label for="identification-number">Número de identificación @if($page==='profile')<span class="muted">· solo si quieres cambiarlo</span>@endif</label>
<input id="identification-number" name="identification_number" type="text" maxlength="128" autocomplete="off" spellcheck="false" @required($page==='register') aria-describedby="document-help">
<p id="document-help" class="help">El país emisor puede ser distinto de tu nacionalidad. Validar el formato no acredita la identidad del titular.</p></div>
