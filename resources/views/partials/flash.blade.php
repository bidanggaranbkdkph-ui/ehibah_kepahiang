@foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $key => $cls)
    @if (session($key))
        <div class="alert {{ $cls }}" role="alert"><svg class="i"><use href="#{{ $cls === 'success' ? 'i-check' : ($cls === 'error' ? 'i-alert' : 'i-info') }}"/></svg><div>{{ session($key) }}</div></div>
    @endif
@endforeach
@if ($errors->any())
    <div class="alert error" role="alert"><svg class="i"><use href="#i-alert"/></svg>
        <div><b>Periksa kembali isian Anda:</b>
            <ul>@foreach (collect($errors->all())->unique()->take(8) as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    </div>
@endif
