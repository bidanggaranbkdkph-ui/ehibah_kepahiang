@php
    $user = auth()->user();
    $toneBtn = ['primary' => 'primary', 'danger' => 'danger', 'warning' => 'warning'];
    $lastTech = $techVerification;
@endphp
<div class="card tone-green">
    <div class="card-h"><svg class="i"><use href="#i-check"/></svg><h3>Tindakan</h3></div>
    @forelse ($actions as $key => $def)
        @php $bl = $blockers[$key] ?? []; $needNote = $def['note'] ?? false; $form = $def['form'] ?? null; $fid = 'f-'.$key; @endphp
        <div style="margin-bottom:.9rem;{{ ! $loop->last ? 'padding-bottom:.9rem;border-bottom:1px dashed var(--line)' : '' }}">
            @if ($bl)
                <div class="blockers"><b>Prasyarat "{{ $def['label'] }}" belum terpenuhi:</b><ul>@foreach ($bl as $b)<li>{{ $b }}</li>@endforeach</ul></div>
            @endif

            @if ($needNote)
                <button type="button" class="btn {{ $toneBtn[$def['tone'] ?? 'primary'] ?? '' }} block" data-note-toggle="{{ $fid }}">{{ $def['label'] }}</button>
                <form id="{{ $fid }}" method="POST" action="{{ route('applications.action', [$app, $key]) }}" hidden class="stack mt-s" data-confirm="{{ $def['label'] }}? Tindakan ini tercatat pada audit trail.">@csrf
                    <input type="hidden" name="lock_version" value="{{ $app->lock_version }}">
                    <div><label>Alasan / catatan <span class="req">*</span></label><textarea name="note" rows="3" required></textarea></div>
                    <button class="btn {{ $toneBtn[$def['tone'] ?? 'primary'] ?? '' }} block" type="submit">Konfirmasi: {{ $def['label'] }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('applications.action', [$app, $key]) }}" class="stack" data-confirm="{{ $def['label'] }}?">@csrf
                    <input type="hidden" name="lock_version" value="{{ $app->lock_version }}">
                    @switch($form)
                        @case('admin_checklist')
                            <div><b class="small">Checklist kriteria hibah (Pasal 2)</b>
                                @foreach ($criteria as $k => $label)<label class="check" style="margin:.35rem 0"><input type="checkbox" name="criteria[{{ $k }}]" value="1" @checked(old('criteria.'.$k))><span>{{ $label }}</span></label>@endforeach</div>
                            <div><label>Catatan</label><input type="text" name="note"></div>
                            @break
                        @case('recommendation')
                            @if ($lastTech)<div class="small">Hasil teknis: skor <b>{{ $lastTech->score }}</b> — {{ $lastTech->result === 'LULUS' ? 'layak' : 'tidak layak' }}</div>@endif
                            <div><label>No. surat rekomendasi <span class="req">*</span></label><input type="text" name="letter_no" value="{{ old('letter_no') }}" required></div>
                            <div><label>Tanggal surat <span class="req">*</span></label><input type="date" name="letter_date" value="{{ old('letter_date', now()->toDateString()) }}" required></div>
                            <div><label>Nilai rekomendasi (Rp) <span class="req">*</span></label><input type="number" name="amount_recommended" min="1" max="{{ $app->amount_requested }}" value="{{ old('amount_recommended', $app->amount_requested) }}" required><div class="hint">Maks. {{ rupiah($app->amount_requested) }}</div></div>
                            <div><label>Catatan</label><input type="text" name="note"></div>
                            @break
                        @case('approval')
                            <div><label>Keputusan Bupati <span class="req">*</span></label><select name="decree_id" required><option value="">— pilih —</option>@foreach ($decrees as $dc)<option value="{{ $dc->id }}" @selected(old('decree_id') == $dc->id)>{{ $dc->decree_no }} · {{ tgl($dc->decree_date, true) }}</option>@endforeach</select>
                                @if ($decrees->isEmpty())<div class="hint" style="color:var(--red)">Belum ada Keputusan untuk tahun ini — <a href="{{ route('decrees.index') }}">buat di menu Keputusan Bupati</a>.</div>@endif</div>
                            <div><label>Nilai ditetapkan (Rp) <span class="req">*</span></label><input type="number" name="amount_approved" min="1" max="{{ $app->allocation?->amount ?? $app->amount_recommended }}" value="{{ old('amount_approved', $app->allocation?->amount ?? $app->amount_recommended) }}" required><div class="hint">Maks. sesuai alokasi {{ rupiah($app->allocation?->amount) }}</div></div>
                            <div><label>Catatan</label><input type="text" name="note"></div>
                            @break
                        @case('budget_confirm')
                            <div><label>Kode rekening <span class="req">*</span></label><input type="text" name="account_code" value="{{ old('account_code', $app->allocation?->account_code) }}" required></div>
                            <div><label>Referensi DPA <span class="req">*</span></label><input type="text" name="dpa_ref" value="{{ old('dpa_ref', $app->allocation?->dpa_ref) }}" required></div>
                            @break
                        @case('nphd_sign')
                            <div><label>Metode tanda tangan <span class="req">*</span></label><select name="signature_method" required><option value="BASAH">Tanda tangan basah</option><option value="TTE">TTE (BSrE)</option></select></div>
                            <div><label>Tanggal penandatanganan <span class="req">*</span></label><input type="date" name="signed_at" value="{{ old('signed_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
                            <div><label>Referensi TTE (bila ada)</label><input type="text" name="signature_ref"></div>
                            @break
                        @default
                            @if ($key === 'submit')<div class="hint">Setelah diajukan, usulan terkunci sampai diperiksa OPD. Pastikan seluruh dokumen wajib sudah diunggah.</div>@endif
                    @endswitch
                    <button class="btn {{ $toneBtn[$def['tone'] ?? 'primary'] ?? '' }} block" type="submit"><svg class="i"><use href="#i-check"/></svg>{{ $def['label'] }}</button>
                </form>
            @endif
        </div>
    @empty
        <div class="muted small">
            @if ($s->isTerminal()) Usulan telah {{ mb_strtolower($s->label()) }}; tidak ada tindakan lanjutan.
            @else Saat ini menunggu <b>{{ collect($s->responsibleRoles())->map(fn ($r) => config('ehibah.roles.'.$r))->implode(' / ') ?: '—' }}</b>. Tidak ada tindakan yang tersedia untuk peran Anda pada status "{{ $s->label() }}".
            @endif
        </div>
    @endforelse
</div>
