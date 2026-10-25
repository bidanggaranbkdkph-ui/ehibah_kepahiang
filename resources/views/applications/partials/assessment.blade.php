@php use App\Enums\Status; @endphp
@php
    $adm = $app->verifications->where('stage', 'ADMINISTRASI');
    $tek = $app->verifications->where('stage', 'TEKNIS');
    $resultBadge = ['LULUS' => ['Lulus', 'green'], 'DIKEMBALIKAN' => ['Dikembalikan', 'amber'], 'DITOLAK' => ['Ditolak / tidak layak', 'red']];
@endphp
<div class="stack">
    <div class="card">
        <div class="card-h"><h3>Verifikasi administrasi (OPD)</h3><span class="muted small">SOP 3 · Pemeriksaan kelengkapan — checklist kriteria Perbup Pasal 2</span></div>
        @forelse ($adm as $v)
            <div class="doc-item" style="grid-template-columns:1fr auto"><div>
                <b>Putaran {{ $v->round }} · {{ $v->verifier->name }}</b><div class="small muted">{{ tgljam($v->verified_at) }}</div>
                @if ($v->checklist) <ul class="small" style="margin:.3rem 0 0 1rem;padding:0">@foreach ($criteria as $k => $label)<li>{{ ($v->checklist['criteria'][$k] ?? false) ? '✔' : '✘' }} {{ $label }}</li>@endforeach</ul>@endif
                @if ($v->notes)<div class="small">Catatan: {{ $v->notes }}</div>@endif
            </div><span class="badge {{ $resultBadge[$v->result][1] }}">{{ $resultBadge[$v->result][0] }}</span></div>
        @empty <div class="empty small">Belum ada hasil verifikasi administrasi.</div> @endforelse
    </div>

    <div class="card">
        <div class="card-h"><h3>Verifikasi teknis & penilaian (Tim Verifikasi)</h3><span class="muted small">SOP 4 · skor minimal layak: {{ \App\Support\Setting::int('tech.min_score') }}</span></div>
        @forelse ($tek as $v)
            <div class="doc-item" style="grid-template-columns:1fr auto"><div>
                <b>Putaran {{ $v->round }} · {{ $v->verifier->name }} @if ($v->score !== null)— skor {{ $v->score }}@endif</b><div class="small muted">{{ tgljam($v->verified_at) }}</div>
                @if (! empty($v->checklist['scores']))<div class="small">@foreach ($techScores as $k => $label){{ $label }}: <b>{{ $v->checklist['scores'][$k] ?? '-' }}</b>@if (! $loop->last) · @endif @endforeach</div>@endif
                @if ($v->notes)<div class="small">Catatan: {{ $v->notes }}</div>@endif
            </div><span class="badge {{ $resultBadge[$v->result][1] }}">{{ $resultBadge[$v->result][0] }}</span></div>
        @empty <div class="empty small">Belum ada penilaian teknis.</div> @endforelse

        @if ($s === Status::TECH_CHECK && $user->hasRole('VERIFIKATOR'))
            <details class="inline" open style="margin-top:.8rem"><summary><svg class="i"><use href="#i-edit"/></svg>Isi penilaian teknis</summary>
                <div class="body"><form method="POST" action="{{ route('process.technical', $app) }}" class="stack">@csrf
                    <div class="form-grid">
                        @foreach ($techScores as $k => $label)
                            <div><label>{{ $label }} (0–100)</label><input type="number" name="scores[{{ $k }}]" min="0" max="100" required value="{{ old('scores.'.$k) }}"></div>
                        @endforeach
                        <div class="full"><label>Catatan / berita acara</label><textarea name="notes" rows="2">{{ old('notes') }}</textarea></div>
                    </div>
                    <button class="btn primary" type="submit">Simpan penilaian</button>
                    <div class="hint">Setelah dinilai layak, OPD dapat menerbitkan rekomendasi. Bila tidak layak, gunakan "Tolak (tidak layak)" atau "Kembalikan" pada panel aksi.</div>
                </form></div></details>
        @endif
        @if ($app->verifications->isNotEmpty() && $user->hasRole('OPD', 'VERIFIKATOR', 'TAPD', 'INSPEKTORAT'))
            <a class="btn sm mt" target="_blank" href="{{ route('print', [$app, 'berita-acara']) }}"><svg class="i"><use href="#i-print"/></svg>Berita acara verifikasi (PDF)</a>
        @endif
    </div>

    @if ($app->recommendation)
        <div class="card tone-green"><div class="card-h"><h3>Rekomendasi OPD</h3></div>
            <dl class="def"><dt>Nomor surat</dt><dd>{{ $app->recommendation->letter_no }}</dd><dt>Tanggal</dt><dd>{{ tgl($app->recommendation->letter_date) }}</dd>
                <dt>Nilai rekomendasi</dt><dd><b>{{ rupiah($app->recommendation->amount_recommended) }}</b></dd><dt>Diterbitkan oleh</dt><dd>{{ $app->recommendation->issuer->name }}</dd></dl></div>
    @endif
</div>
