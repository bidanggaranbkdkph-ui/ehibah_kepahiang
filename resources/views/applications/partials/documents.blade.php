@php use App\Enums\Status; @endphp
@php
    $stateLabel = ['MISSING' => ['Belum diunggah', 'amber'], 'EXPIRED' => ['Kedaluwarsa', 'red'], 'UPLOADED' => ['Menunggu pemeriksaan', 'blue'], 'SESUAI' => ['Sesuai', 'green'], 'TIDAK_SESUAI' => ['Tidak sesuai', 'red']];
    $maxBytes = \App\Support\Setting::int('upload.max_mb') * 1048576;
    $mimes = implode(', ', array_map('strtoupper', \App\Support\Setting::get('upload.mimes')));
    $opdReview = $isOpd && in_array($app->status, [Status::ADMIN_CHECK, Status::BUDGETED, Status::NPHD_SIGNED, Status::DISBURSED, Status::LPJ_REVIEW], true);
@endphp
<div class="stack">
@foreach ($checklists as $key => $cl)
    <div class="card">
        <div class="card-h"><h3>{{ $cl['label'] }}</h3><span class="badge {{ $cl['progress']['percent'] === 100 ? 'green' : 'amber' }}">{{ $cl['progress']['done'] }}/{{ $cl['progress']['total'] }} wajib terpenuhi</span></div>
        <div class="bar" style="margin-bottom:.8rem"><i style="width:{{ $cl['progress']['percent'] }}%"></i></div>
        @foreach ($cl['items'] as $it)
            @php $r = $it['requirement']; $d = $it['document']; $st = $it['state']; $canRev = $d && $d->is_current && ($opdReview || ($user->hasRole('BPKAD') && in_array($d->stage, ['PENCAIRAN', 'PENYERAHAN']))); @endphp
            <div class="doc-item {{ $st }}">
                <span class="doc-ic"><svg class="i"><use href="#{{ $st === 'MISSING' ? 'i-upload' : ($st === 'SESUAI' ? 'i-check' : 'i-file') }}"/></svg></span>
                <div style="min-width:0">
                    <b>{{ $r->name }} @if ($r->is_mandatory)<span class="req" style="color:var(--red)">*</span>@endif</b>
                    <div class="small muted">
                        <span class="badge {{ $stateLabel[$st][1] }}">{{ $stateLabel[$st][0] }}</span>
                        @if ($r->template_ref)<span class="badge gray">{{ $r->template_ref }}</span>@endif
                        @if ($d) v{{ $d->version }} · {{ $d->original_name }} · {{ number_format($d->size_bytes / 1024, 0) }} KB · {{ tgl($d->created_at, true) }}
                            @if ($d->valid_until) · berlaku s.d. {{ tgl($d->valid_until, true) }}@endif @endif
                    </div>
                    @if ($d && $d->review_note)<div class="small" style="color:var(--red)">Catatan pemeriksa: {{ $d->review_note }}</div>@endif
                    @if ($canUpload[$cl['stage']])
                        <details class="inline" style="margin-top:.4rem"><summary><svg class="i"><use href="#i-upload"/></svg>{{ $d ? 'Unggah versi baru' : 'Unggah' }}</summary>
                            <div class="body">
                                <form method="POST" action="{{ route('documents.store', $app) }}" enctype="multipart/form-data" class="stack">
                                    @csrf
                                    <input type="hidden" name="stage" value="{{ $cl['stage'] }}"><input type="hidden" name="doc_type" value="{{ $r->code }}">
                                    @if ($cl['related'])<input type="hidden" name="related_id" value="{{ $cl['related'][1] }}">@endif
                                    <div><input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" data-max="{{ $maxBytes }}" required><div class="hint">{{ $mimes }} · maks. {{ \App\Support\Setting::int('upload.max_mb') }} MB. Versi lama tetap tersimpan.</div></div>
                                    @if ($r->requires_validity)<div><label>Berlaku sampai <span class="req">*</span></label><input type="date" name="valid_until" required></div>@endif
                                    <button class="btn primary sm" type="submit">Unggah</button>
                                </form></div></details>
                    @endif
                    @if ($canRev)
                        <details class="inline" style="margin-top:.4rem"><summary><svg class="i"><use href="#i-eye"/></svg>Periksa dokumen</summary>
                            <div class="body"><form method="POST" action="{{ route('documents.review', $d) }}" class="stack" data-multi>@csrf
                                <div><label>Catatan (wajib bila tidak sesuai)</label><input type="text" name="review_note" maxlength="1000" value="{{ $d->review_note }}"></div>
                                <div class="row"><button class="btn sm primary" name="result" value="SESUAI">Sesuai</button><button class="btn sm danger" name="result" value="TIDAK_SESUAI">Tidak sesuai</button></div></form></div></details>
                    @endif
                </div>
                @if ($d)<a class="btn sm" href="{{ route('documents.download', $d) }}"><svg class="i"><use href="#i-download"/></svg><span>Unduh</span></a>@endif
            </div>
        @endforeach
    </div>
@endforeach
@php $hist = $app->documents()->where('is_current', false)->orderByDesc('id')->get(); @endphp
@if ($hist->count())
    <div class="card"><div class="card-h"><h3>Riwayat versi dokumen</h3><span class="muted small">versi lama tidak pernah dihapus</span></div>
        <div class="table-wrap"><table class="table-plain"><thead><tr><th>Jenis</th><th>Versi</th><th>Berkas</th><th>Diunggah</th><th>Hash (SHA-256)</th><th></th></tr></thead><tbody>
        @foreach ($hist as $h)<tr><td>{{ $h->doc_type }}</td><td>v{{ $h->version }}</td><td>{{ $h->original_name }}</td><td>{{ tgljam($h->created_at) }}</td><td class="mono small">{{ substr($h->file_hash, 0, 14) }}…</td><td><a class="btn sm" href="{{ route('documents.download', $h) }}">Unduh</a></td></tr>@endforeach
        </tbody></table></div></div>
@endif
</div>
