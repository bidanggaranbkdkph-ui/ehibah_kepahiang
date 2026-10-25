@php use App\Enums\Status; @endphp
<div class="stack">
    {{-- Pertimbangan TAPD & alokasi pagu --}}
    <div class="card">
        <div class="card-h"><h3>Pertimbangan TAPD & alokasi anggaran</h3><span class="muted small">SOP 6–7 · Pasal 6</span></div>
        @if ($app->tapdReview)
            <dl class="def"><dt>Nilai dipertimbangkan</dt><dd><b>{{ rupiah($app->tapdReview->amount_considered) }}</b></dd><dt>Catatan</dt><dd>{{ $app->tapdReview->notes ?? '—' }}</dd>
                <dt>Pereviu</dt><dd>{{ $app->tapdReview->reviewer->name }} · {{ tgljam($app->tapdReview->reviewed_at) }}</dd></dl>
        @else <div class="empty small">Belum ada pertimbangan TAPD.</div> @endif

        @if ($app->allocation)
            <hr>
            <dl class="def"><dt>Alokasi pagu</dt><dd><b>{{ rupiah($app->allocation->amount) }}</b> <span class="badge {{ ['RESERVED' => 'blue', 'CONFIRMED' => 'green', 'RELEASED' => 'gray'][$app->allocation->status] }}">{{ ['RESERVED' => 'Dicadangkan', 'CONFIRMED' => 'Dikonfirmasi DPA', 'RELEASED' => 'Dilepas'][$app->allocation->status] }}</span></dd>
                <dt>Kode rekening</dt><dd class="mono">{{ $app->allocation->account_code ?? '—' }}</dd><dt>Referensi DPA</dt><dd>{{ $app->allocation->dpa_ref ?? '—' }}</dd>
                @if ($app->allocation->release_reason)<dt>Alasan dilepas</dt><dd>{{ $app->allocation->release_reason }}</dd>@endif</dl>
        @endif

        @if ($s === Status::TAPD_REVIEW && $user->hasRole('TAPD'))
            <details class="inline" open style="margin-top:.8rem"><summary><svg class="i"><use href="#i-wallet"/></svg>Catat pertimbangan & cadangkan dana</summary>
                <div class="body"><form method="POST" action="{{ route('process.tapd', $app) }}" class="stack">@csrf
                    <div class="form-grid">
                        <div><label>Nilai yang dipertimbangkan (Rp) <span class="req">*</span></label><input type="number" name="amount" min="1" max="{{ $app->amount_recommended }}" value="{{ old('amount', $app->tapdReview?->amount_considered ?? $app->amount_recommended) }}" required>
                            <div class="hint">Maksimal sama dengan nilai rekomendasi ({{ rupiah($app->amount_recommended) }}). Sistem memblokir bila melebihi sisa pagu OPD.</div></div>
                        <div class="full"><label>Pertimbangan</label><textarea name="notes" rows="2">{{ old('notes', $app->tapdReview?->notes) }}</textarea></div>
                    </div>
                    <button class="btn primary">Simpan pertimbangan</button></form></div></details>
        @endif
    </div>

    {{-- Keputusan Bupati --}}
    <div class="card {{ $app->decision ? 'tone-green' : '' }}">
        <div class="card-h"><h3>Keputusan Bupati</h3><span class="muted small">Pasal 7 · daftar penerima & besaran</span></div>
        @if ($app->decision)
            <dl class="def"><dt>No. Keputusan</dt><dd><b>{{ $app->decision->decree->decree_no }}</b></dd><dt>Tanggal</dt><dd>{{ tgl($app->decision->decree->decree_date) }}</dd>
                <dt>Judul</dt><dd>{{ $app->decision->decree->title }}</dd><dt>Perda APBD</dt><dd>{{ $app->decision->decree->perda_ref }}</dd><dt>Perkada penjabaran</dt><dd>{{ $app->decision->decree->perkada_ref }}</dd>
                <dt>Nilai ditetapkan</dt><dd><b>{{ rupiah($app->decision->amount_approved) }}</b></dd></dl>
        @elseif ($s->isTerminal() && $app->rejection_reason) <div class="small">Ditolak — {{ $app->rejection_reason }}</div>
        @else <div class="empty small">Belum ditetapkan. Penetapan memerlukan pertimbangan TAPD, alokasi anggaran, dan nomor/tanggal Keputusan Bupati.</div> @endif
    </div>
</div>
