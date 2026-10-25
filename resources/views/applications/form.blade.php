@extends('layouts.app')
@php $editing = $app->exists; @endphp
@section('title', $editing ? 'Ubah usulan' : 'Usulan baru')
@section('heading', $editing ? 'Ubah Usulan' : 'Usulan Hibah Baru')
@section('content')
@php
    $items = old('items', $editing ? $app->items->map(fn ($i) => ['description' => $i->description, 'quantity' => (float) $i->quantity, 'unit' => $i->unit, 'unit_price' => $i->unit_price])->all() : []);
    $committee = old('committee', $app->committee ?? []);
    $val = fn ($f, $d = null) => old($f, $editing ? $app->$f : $d);
    $dt = fn ($f) => old($f, $editing && $app->$f ? $app->$f->toDateString() : null);
@endphp
<div class="page-head"><div><h1>{{ $editing ? 'Ubah usulan' : 'Usulan hibah baru' }}</h1><p>Perbup Pasal 6: usulan tertulis memuat latar belakang, maksud/tujuan, sasaran, program/kegiatan, RAB, dan susunan panitia.</p></div></div>

@if (! $org->bank_account_no)
    <div class="alert warning"><svg class="i"><use href="#i-alert"/></svg><div>Data rekening lembaga belum lengkap. Hibah uang memerlukannya — <a href="{{ route('profile.edit') }}">lengkapi profil lembaga</a>.</div></div>
@endif

<form method="POST" action="{{ $editing ? route('applications.update', $app) : route('applications.store') }}">
    @csrf @if ($editing) @method('PUT') <input type="hidden" name="lock_version" value="{{ $app->lock_version }}"> @endif

    <div class="card" style="margin-bottom:1rem">
        <div class="card-h"><h3>1 · Identitas usulan</h3></div>
        <div class="form-grid">
            <div class="full"><label>Judul usulan <span class="req">*</span></label><input type="text" name="title" value="{{ $val('title') }}" required maxlength="250"></div>
            <div><label>Bentuk hibah <span class="req">*</span></label><select name="grant_type_id" required>@foreach ($types as $t)<option value="{{ $t->id }}" @selected($val('grant_type_id') == $t->id)>{{ $t->name }}</option>@endforeach</select>
                <div class="hint">Hibah uang: jalur SP2D. Hibah barang: jalur BAST. Bantuan sosial bukan bagian dari E-Hibah.</div></div>
            <div><label>OPD pemilik urusan <span class="req">*</span></label><select name="opd_id" required><option value="">— pilih —</option>@foreach ($opds as $o)<option value="{{ $o->id }}" @selected($val('opd_id') == $o->id)>{{ $o->name }}</option>@endforeach</select>
                <div class="hint">Perbup Pasal 2: hibah dianggarkan pada SKPD sesuai urusan.</div></div>
            <div><label>Dasar hukum <span class="req">*</span></label><select name="regulation_id" required>@foreach ($regulations as $r)<option value="{{ $r->id }}" @selected($val('regulation_id') == $r->id)>{{ \Illuminate\Support\Str::limit($r->title, 70) }}</option>@endforeach</select>
                <div class="hint">Hanya regulasi yang masih berlaku. Aturan yang telah dicabut tidak tersedia.</div></div>
            <div><label>Lokasi kegiatan <span class="req">*</span></label><input type="text" name="location" value="{{ $val('location') }}" required></div>
            <div><label>Tanggal mulai kegiatan <span class="req">*</span></label><input type="date" name="activity_start" value="{{ $dt('activity_start') }}" required></div>
            <div><label>Tanggal selesai kegiatan <span class="req">*</span></label><input type="date" name="activity_end" value="{{ $dt('activity_end') }}" required>
                <div class="hint">Dasar tenggat LPJ (1 bulan setelah kegiatan selesai, paling lambat 10 Januari tahun berikutnya).</div></div>
        </div>
    </div>

    <div class="card" style="margin-bottom:1rem">
        <div class="card-h"><h3>2 · Uraian usulan</h3></div>
        <div class="form-grid">
            <div class="full"><label>Latar belakang <span class="req">*</span></label><textarea name="background" required>{{ $val('background') }}</textarea></div>
            <div class="full"><label>Maksud dan tujuan <span class="req">*</span></label><textarea name="purpose" required>{{ $val('purpose') }}</textarea></div>
            <div class="full"><label>Sasaran <span class="req">*</span></label><textarea name="target" required>{{ $val('target') }}</textarea></div>
            <div class="full"><label>Program / kegiatan <span class="req">*</span></label><textarea name="program_activity" required>{{ $val('program_activity') }}</textarea></div>
        </div>
    </div>

    <div class="card" style="margin-bottom:1rem">
        <div class="card-h"><h3>3 · Rencana Anggaran Biaya / daftar barang</h3></div>
        <div data-repeat data-min="1">
            <div data-list>
                @foreach ($items as $i => $it)
                    <div class="repeat-row">
                        <div class="wide"><label>Uraian</label><input type="text" name="items[{{ $i }}][description]" value="{{ $it['description'] }}" required></div>
                        <div><label>Volume</label><input type="number" step="0.01" min="0.01" name="items[{{ $i }}][quantity]" value="{{ $it['quantity'] }}" data-q required></div>
                        <div><label>Satuan</label><input type="text" name="items[{{ $i }}][unit]" value="{{ $it['unit'] }}" required></div>
                        <div><label>Harga satuan (Rp)</label><input type="number" min="1" step="1" name="items[{{ $i }}][unit_price]" value="{{ $it['unit_price'] }}" data-p required></div>
                        <div><label>Jumlah</label><div class="mono" data-sub style="padding:.6rem 0">—</div></div>
                        <button type="button" class="btn sm danger" data-remove aria-label="Hapus baris"><svg class="i"><use href="#i-x"/></svg></button>
                    </div>
                @endforeach
            </div>
            <template><div class="repeat-row">
                <div class="wide"><label>Uraian</label><input type="text" name="items[0][description]" required></div>
                <div><label>Volume</label><input type="number" step="0.01" min="0.01" name="items[0][quantity]" value="1" data-q required></div>
                <div><label>Satuan</label><input type="text" name="items[0][unit]" value="paket" required></div>
                <div><label>Harga satuan (Rp)</label><input type="number" min="1" step="1" name="items[0][unit_price]" data-p required></div>
                <div><label>Jumlah</label><div class="mono" data-sub style="padding:.6rem 0">—</div></div>
                <button type="button" class="btn sm danger" data-remove aria-label="Hapus baris"><svg class="i"><use href="#i-x"/></svg></button>
            </div></template>
            <button type="button" class="btn sm" data-add><svg class="i"><use href="#i-plus"/></svg>Tambah baris</button>
            <div class="total-bar mt"><span>Total nilai usulan (dihitung otomatis)</span><b data-total>Rp 0</b></div>
        </div>
    </div>

    <div class="card" style="margin-bottom:1rem">
        <div class="card-h"><h3>4 · Susunan organisasi / panitia</h3></div>
        <div data-repeat data-min="1">
            <div data-list>
                @foreach ($committee as $i => $c)
                    <div class="repeat-row c"><div><label>Nama</label><input type="text" name="committee[{{ $i }}][nama]" value="{{ $c['nama'] }}" required></div><div><label>Jabatan</label><input type="text" name="committee[{{ $i }}][jabatan]" value="{{ $c['jabatan'] }}" required></div><button type="button" class="btn sm danger" data-remove><svg class="i"><use href="#i-x"/></svg></button></div>
                @endforeach
            </div>
            <template><div class="repeat-row c"><div><label>Nama</label><input type="text" name="committee[0][nama]" required></div><div><label>Jabatan</label><input type="text" name="committee[0][jabatan]" required></div><button type="button" class="btn sm danger" data-remove><svg class="i"><use href="#i-x"/></svg></button></div></template>
            <button type="button" class="btn sm" data-add><svg class="i"><use href="#i-plus"/></svg>Tambah anggota</button>
        </div>
    </div>

    <div class="card" style="margin-bottom:1rem">
        <div class="card-h"><h3>5 · Pernyataan</h3></div>
        <div class="stack">
            <label class="check"><input type="hidden" name="funding_declaration" value="0"><input type="checkbox" name="funding_declaration" value="1" @checked($val('funding_declaration'))> <span>Kegiatan ini tidak menerima pendanaan ganda / tumpang tindih dengan APBN atau sumber lain (Pasal 4).</span></label>
            <label class="check"><input type="hidden" name="is_recurring" value="0"><input type="checkbox" name="is_recurring" value="1" @checked($val('is_recurring'))> <span>Hibah serupa telah diterima pada tahun sebelumnya (diberikan berulang).</span></label>
            <div><label>Alasan pengecualian (wajib bila berulang)</label><textarea name="recurring_reason" rows="2">{{ $val('recurring_reason') }}</textarea><div class="hint">Pasal 2: hibah tidak diberikan terus-menerus kecuali dengan pengecualian yang beralasan.</div></div>
        </div>
    </div>

    <div class="row"><button class="btn primary" type="submit"><svg class="i"><use href="#i-check"/></svg>{{ $editing ? 'Simpan perubahan' : 'Simpan sebagai draf' }}</button><a class="btn" href="{{ $editing ? route('applications.show', $app) : route('applications.index') }}">Batal</a>
        <span class="muted small">Dokumen diunggah di halaman usulan setelah draf tersimpan.</span></div>
</form>
@endsection
