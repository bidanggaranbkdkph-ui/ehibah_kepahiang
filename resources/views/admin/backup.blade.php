@extends('layouts.app')
@section('title', 'Backup')
@section('heading', 'Backup & Pemulihan')
@section('content')
<div class="page-head"><div><h1>Backup & pemulihan</h1><p>Backup dijadwalkan otomatis (database harian, penuh mingguan). Simpan salinan di lokasi terpisah dan uji pemulihan berkala — lihat docs/OPERASIONAL.md.</p></div></div>
<div class="grid g-main">
<div class="card pad0"><div class="table-wrap"><table class="responsive">
    <thead><tr><th>Waktu</th><th>Jenis</th><th>Status</th><th class="num">Ukuran</th><th>Verifikasi</th><th></th></tr></thead><tbody>
    @forelse ($logs as $l)
        <tr><td data-label="Waktu">{{ tgljam($l->started_at) }}</td><td data-label="Jenis">{{ $l->type }}</td><td data-label="Status"><span class="badge {{ $l->status === 'BERHASIL' ? 'green' : 'red' }}">{{ $l->status }}</span>@if ($l->message && $l->status !== 'BERHASIL')<div class="small" style="color:var(--red)">{{ \Illuminate\Support\Str::limit($l->message, 120) }}</div>@endif</td>
            <td data-label="Ukuran" class="num">{{ $l->size_bytes ? number_format($l->size_bytes / 1024, 1, ',', '.').' KB' : '—' }}</td>
            <td data-label="Verifikasi">@if ($l->verified_at)<span class="badge green">OK {{ tgl($l->verified_at, true) }}</span>@elseif ($l->status === 'BERHASIL')<span class="badge amber">belum</span>@endif</td>
            <td>@if ($l->status === 'BERHASIL')<form method="POST" action="{{ route('admin.backup.verify', $l) }}">@csrf<button class="btn sm">Uji keutuhan</button></form>@endif</td></tr>
    @empty <tr><td colspan="6"><div class="empty">Belum ada backup.</div></td></tr> @endforelse
    </tbody></table></div></div>
<div class="card"><div class="card-h"><h3>Jalankan backup</h3></div>
    <form method="POST" action="{{ route('admin.backup.run') }}" class="stack" data-confirm="Jalankan backup sekarang?">@csrf
        <div><label>Jenis</label><select name="type"><option value="FULL">Penuh (database + dokumen)</option><option value="DB">Database saja</option><option value="FILES">Dokumen saja</option></select></div>
        <button class="btn primary"><svg class="i"><use href="#i-folder"/></svg>Backup sekarang</button></form>
    <hr><dl class="def" style="grid-template-columns:90px 1fr"><dt>Lokasi</dt><dd class="mono small" style="word-break:break-all">{{ $path }}</dd><dt>Retensi</dt><dd>{{ \App\Support\Setting::int('backup.retention_days') }} hari</dd></dl>
    <div class="hint">Pemulihan: <span class="mono">pg_restore -d ehibah --clean &lt;file.dump&gt;</span> (langkah rinci di docs/OPERASIONAL.md).</div></div>
</div>
@endsection
