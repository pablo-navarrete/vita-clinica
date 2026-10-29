@foreach (['success', 'error', 'info'] as $messageType)
    @if (session($messageType))
        <span hidden data-record-toast="{{ $messageType }}" @if($messageType === 'error') data-record-errors @endif>{{ session($messageType) }}</span>
    @endif
@endforeach
@foreach ($errors->all() as $message)
    <span hidden data-record-toast="error" data-record-validation data-record-errors>{{ $message }}</span>
@endforeach
