@php use App\Enums\Status; $user = auth()->user(); $bpkad = $user->hasRole('BPKAD'); @endphp
<div class="stack">
    <div class="card">
        <div class="card-h"><h3>Rencana penyaluran hibah uang</h3><span class="muted small">Pasal 9 · LS · SPP → SPM → SP2D-LS → transfer RKUD</span></div>
        <div class="row" style="margin-bottom:.7rem">
            <span class="badge {{ $app->disbursement_scheme === 'FULL' ? 'gold' : 'blue' }}">Skema: {{ $app->disbursement_scheme === 'FULL' ? '100% sekaligus (pengecualian)' : 'Bertahap '.\App\Support\Setting::int('disbursement.stage1_percent').'% / '.(100 - \App\Support\Setting::int('disbursement.stage1_percent')).'%' }}</span>
            <span class="muted small">Nilai NPHD {{ rupiah($app->activeNphd()?->amount) }} · tersalur {{ rupiah($app->disbursedTotal()) }}</span>
        </div>
        @foreach ($plan as $stage => $p)
            @php $d = $p['disbursement']; @endphp
            <div class="card flat {{ $d?->status === 'DISALURKAN' ? 'tone-green' : '' }}" style="margin-bottom:.7rem">
                <div class="card-h"><h4>Tahap {{ $stage }} — {{ rtrim(rtrim(number_format($p['percentage'], 2), '0'), '.') }}%</h4><span class="sp"></span><b>{{ rupiah($p['amount']) }}</b>
                    @if ($d)<span class="badge {{ ['DIAJUKAN' => 'blue', 'DISALURKAN' => 'green'][$d->status] ?? 'gray' }}">{{ ['DIAJUKAN' => 'Menunggu SP2D', 'DISALURKAN' => 'Disalurkan'][$d->status] ?? $d->status }}</span>@endif</div>
                @if ($d)
                    <dl class="def"><dt>SPP</dt><dd>{{ $d->spp_no ?? '—' }} {{ $d->spp_date ? '· '.tgl($d->spp_date) : '' }}</dd>
                        @if ($d->status === 'DISALURKAN')<dt>SPM</dt><dd>{{ $d->spm_no }}</dd><dt>SP2D-LS</dt><dd>{{ $d->sp2d_no }} · {{ tgl($d->sp2d_date) }}</dd><dt>Bukti transfer RKUD</dt><dd>{{ $d->rkud_ref }}</dd><dt>Rekening tujuan</dt><dd>{{ $d->bank_account_ref }}</dd><dt>Dibayar</dt><dd>{{ tgljam($d->paid_at) }}</dd>@endif</dl>
                    @if ($d->status === 'DIAJUKAN' && $bpkad)
                        <details class="inline" open><summary><svg class="i"><use href="#i-wallet"/></svg>Proses SPM / SP2D-LS</summary><div class="body">
                            <form method="POST" action="{{ route('process.disb.process', $d) }}" class="stack" data-confirm="Catat penyaluran ini sebagai sudah dibayar dari RKUD?">@csrf
                                <div class="form-grid">
                                    <div><label>No. SPM <span class="req">*</span></label><input type="text" name="spm_no" required></div>
                                    <div><label>No. SP2D-LS <span class="req">*</span></label><input type="text" name="sp2d_no" required></div>
                                    <div><label>Tanggal SP2D <span class="req">*</span></label><input type="date" name="sp2d_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                                    <div><label>Bukti transfer RKUD <span class="req">*</span></label><input type="text" name="rkud_ref" required></div>
                                    <div class="full"><label>Rekening tujuan <span class="req">*</span></label><input type="text" name="bank_account_ref" value="{{ $app->applicant->bank_name }} {{ $app->applicant->bank_account_no }} a.n. {{ $app->applicant->bank_account_name }}" required></div>
                                </div><button class="btn primary">Catat penyaluran</button></form>
                            <form method="POST" action="{{ route('process.disb.reject', $d) }}" class="row mt" data-confirm="Tolak pengajuan penyaluran ini?">@csrf
                                <input type="text" name="reason" placeholder="Alasan penolakan (wajib)" required style="flex:1"><button class="btn danger sm">Tolak pengajuan</button></form></div></details>
                    @endif
                @elseif ($p['blockers'])
                    <div class="blockers"><b>Belum dapat diajukan:</b><ul>@foreach ($p['blockers'] as $b)<li>{{ $b }}</li>@endforeach</ul></div>
                @elseif ($isOpd)
                    <form method="POST" action="{{ route('process.disb.request', $app) }}" class="stack" data-confirm="Ajukan penyaluran tahap {{ $stage }} sebesar {{ rupiah($p['amount']) }} ke BPKAD?">@csrf
                        <input type="hidden" name="stage" value="{{ $stage }}"><input type="hidden" name="idempotency_key" data-uuid>
                        <div class="form-grid"><div><label>No. SPP</label><input type="text" name="spp_no"></div><div><label>Tanggal SPP</label><input type="date" name="spp_date" value="{{ now()->toDateString() }}"></div></div>
                        <button class="btn primary">Ajukan penyaluran tahap {{ $stage }}</button>
                        <div class="hint">Satu pengajuan per tahap; klik ganda/pengiriman ulang tidak membuat pembayaran ganda.</div></form>
                @else <div class="muted small">Menunggu OPD mengajukan penyaluran.</div> @endif
            </div>
        @endforeach
    </div>

    {{-- Pengecualian 100% --}}
    @if ($app->disbursement_scheme === 'STAGED' && $app->disbursements->whereIn('status', ['DIAJUKAN', 'DISALURKAN'])->isEmpty())
    <div class="card">
        <div class="card-h"><h3>Pengecualian pencairan 100%</h3><span class="muted small">Pasal 9 ayat (3) · instansi vertikal / event tidak bertahap</span></div>
        @if ($app->full_exception_reason && ! $app->full_exception_by)
            <div class="alert warning"><svg class="i"><use href="#i-clock"/></svg><div>Menunggu otorisasi BPKAD: {{ $app->full_exception_reason }}</div></div>
            @if ($bpkad)
                <form method="POST" action="{{ route('process.exception.decide', $app) }}" class="stack">@csrf
                    <div><label>Catatan otorisasi</label><input type="text" name="note"></div>
                    <div class="row"><button class="btn primary" name="decision" value="approve" data-confirm="Otorisasi pencairan 100% sekaligus?">Otorisasi</button><button class="btn danger" name="decision" value="deny">Tolak</button></div></form>
            @endif
        @elseif ($isOpd)
            <details class="inline"><summary>Ajukan pengecualian</summary><div class="body"><form method="POST" action="{{ route('process.exception.request', $app) }}" class="stack">@csrf
                <div><label>Dasar pengecualian <span class="req">*</span></label><select name="basis" required><option value="INSTANSI_VERTIKAL">Penerima instansi vertikal</option><option value="EVENT">Event yang tidak memungkinkan bertahap</option></select></div>
                <div><label>Alasan (min. 15 karakter) <span class="req">*</span></label><textarea name="reason" rows="3" required minlength="15"></textarea></div>
                <button class="btn warning">Ajukan ke BPKAD</button><div class="hint">Pengecualian tidak berlaku otomatis; memerlukan otorisasi BPKAD dan tercatat pada audit trail.</div></form></div></details>
        @else <div class="muted small">Tidak ada pengajuan pengecualian.</div> @endif
    </div>
    @elseif ($app->disbursement_scheme === 'FULL')
        <div class="alert info"><svg class="i"><use href="#i-info"/></svg><div>Pengecualian 100% telah diotorisasi BPKAD pada {{ tgljam($app->full_exception_at) }}. Dasar: {{ $app->full_exception_reason }}</div></div>
    @endif
</div>
