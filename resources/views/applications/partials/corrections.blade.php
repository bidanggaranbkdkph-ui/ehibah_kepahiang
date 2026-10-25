@php use App\Enums\Status; @endphp
@php
    $user = auth()->user();
    $svc = app(\App\Services\CorrectionService::class);
    $labels = ['title' => 'Judul usulan', 'purpose' => 'Maksud & tujuan', 'target' => 'Sasaran', 'location' => 'Lokasi', 'activity_start' => 'Tanggal mulai kegiatan', 'activity_end' => 'Tanggal selesai kegiatan',
        'nphd_no' => 'Nomor NPHD', 'nphd_date' => 'Tanggal NPHD', 'spp_no' => 'No. SPP', 'spm_no' => 'No. SPM', 'sp2d_no' => 'No. SP2D', 'sp2d_date' => 'Tanggal SP2D', 'rkud_ref' => 'Bukti transfer RKUD', 'bank_account_ref' => 'Rekening tujuan',
        'decree_no' => 'Nomor Keputusan Bupati', 'decree_date' => 'Tanggal Keputusan'];
    // target yang dapat dikoreksi pada usulan ini
    $targets = [];
    foreach (['title', 'purpose', 'target', 'location', 'activity_start', 'activity_end'] as $f) $targets["application:{$app->id}:$f"] = 'Usulan — '.$labels[$f].' (saat ini: '.\Illuminate\Support\Str::limit((string) ($app->$f instanceof \DateTimeInterface ? $app->$f->format('Y-m-d') : $app->$f), 40).')';
    if ($n = $app->activeNphd()) foreach (['nphd_no', 'nphd_date'] as $f) $targets["nphd:{$n->id}:$f"] = 'NPHD — '.$labels[$f].' (saat ini: '.($n->$f instanceof \DateTimeInterface ? $n->$f->format('Y-m-d') : $n->$f).')';
    foreach ($app->disbursements->where('status', 'DISALURKAN') as $d) foreach (['sp2d_no', 'sp2d_date', 'spm_no', 'rkud_ref'] as $f) $targets["disbursement:{$d->id}:$f"] = 'Penyaluran tahap '.$d->stage.' — '.$labels[$f].' (saat ini: '.($d->$f instanceof \DateTimeInterface ? $d->$f->format('Y-m-d') : $d->$f).')';
    if ($app->decision) foreach (['decree_no', 'decree_date'] as $f) $targets["decree:{$app->decision->decree_id}:$f"] = 'Keputusan — '.$labels[$f].' (saat ini: '.($app->decision->decree->$f instanceof \DateTimeInterface ? $app->decision->decree->$f->format('Y-m-d') : $app->decision->decree->$f).')';
    $statusBadge = ['DIAJUKAN' => ['Menunggu persetujuan', 'amber'], 'DISETUJUI' => ['Disetujui & diterapkan', 'green'], 'DITOLAK' => ['Ditolak', 'red']];
@endphp
<div class="stack">
    <div class="alert info"><svg class="i"><use href="#i-info"/></svg><div><b>Koreksi data pasca-kunci.</b> Data yang telah dikunci tidak dapat diubah langsung. Ajukan koreksi dengan alasan; penyetuju yang berwenang (bukan pengaju) akan memutuskan. Nilai lama dan baru tercatat. <b>Nilai uang tidak dapat dikoreksi di sini</b> — batalkan usulan dan ajukan ulang.</div></div>
    <div class="card">
        <div class="card-h"><h3>Daftar koreksi</h3></div>
        @forelse ($app->corrections as $c)
            <div class="doc-item" style="grid-template-columns:1fr auto"><div>
                <b>{{ $c->entity }}.{{ $labels[$c->field] ?? $c->field }}</b>
                <div class="small">"{{ \Illuminate\Support\Str::limit($c->old_value, 80) }}" → <b>"{{ \Illuminate\Support\Str::limit($c->new_value, 80) }}"</b></div>
                <div class="small muted">Alasan: {{ $c->reason }} · diajukan {{ $c->requester->name }}, {{ tgljam($c->created_at) }}</div>
                @if ($c->decided_at)<div class="small muted">Diputuskan {{ $c->decider?->name }}, {{ tgljam($c->decided_at) }} @if ($c->decision_note)— {{ $c->decision_note }}@endif</div>@endif
                @if ($c->status === 'DIAJUKAN' && $svc->canDecide($c, $user))
                    <form method="POST" action="{{ route('corrections.decide', $c) }}" class="row mt-s" data-multi>@csrf
                        <input type="text" name="decision_note" placeholder="Catatan (wajib bila ditolak)" style="flex:1;min-width:160px"><button class="btn sm primary" name="decision" value="approve">Setujui</button><button class="btn sm danger" name="decision" value="reject">Tolak</button></form>
                @elseif ($c->status === 'DIAJUKAN' && $c->requested_by === $user->id)<div class="small muted">Anda adalah pengaju — persetujuan harus oleh pihak lain (prinsip 4-mata).</div>@endif
            </div><span class="badge {{ $statusBadge[$c->status][1] }}">{{ $statusBadge[$c->status][0] }}</span></div>
        @empty <div class="empty small">Belum ada koreksi.</div> @endforelse
    </div>
    @if ($perm('koreksi_data', 'P') && ! in_array($s, [Status::DRAFT, Status::RETURNED], true) && $targets)
        <div class="card"><div class="card-h"><h3>Ajukan koreksi</h3></div>
            <form method="POST" action="{{ route('corrections.store', $app) }}" class="stack">@csrf
                <div><label>Data yang dikoreksi</label><select name="target" required>@foreach ($targets as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                <div><label>Nilai baru <span class="req">*</span></label><input type="text" name="new_value" required><div class="hint">Tanggal ditulis YYYY-MM-DD.</div></div>
                <div><label>Alasan koreksi <span class="req">*</span></label><textarea name="reason" rows="2" required minlength="10"></textarea></div>
                <button class="btn navy">Ajukan koreksi</button></form></div>
    @endif
</div>
