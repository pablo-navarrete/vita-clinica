<div style="border-top:1px solid #dbe2ea; margin-top:10pt; padding-top:6pt;">
    @php($signedPatientIdentity = app(\App\Services\Signatures\EncounterSignatureVerifier::class)->verify($signature, false)['patient'])
    <p>Paciente del documento: {{ $signedPatientIdentity['name'] }} · {{ app(\App\Services\Identification\DocumentIdentityPresenter::class)->label($signedPatientIdentity) }}</p>
    <p><strong>Firma Electrónica Simple (VITA)</strong></p>
    <p>{{ $signature->identity['name'] }} · {{ app(\App\Services\Signatures\SignerIdentitySnapshot::class)->label($signature->identity) }}</p>
    <p>Firmado el {{ $signature->signed_at->timezone('America/Santiago')->format('d/m/Y H:i:s') }} (America/Santiago)</p>
    <p class="muted">Documento #{{ $signature->document_version_id }} · SHA-256: {{ $signature->content_hash }}</p>
    @foreach(app(\App\Services\Signatures\SignedAttachmentStore::class)->visualImages($signature) as $visual)
        <div style="display:inline-block; margin:6pt 12pt 0 0;">
            <img src="{{ $visual['src'] }}" style="max-width:180px; max-height:90px;" alt="{{ $visual['kind'] === 'stamp' ? 'Timbre profesional' : 'Firma visual' }}">
        </div>
    @endforeach
    <p class="muted">Firma del médico tratante.</p>
</div>
