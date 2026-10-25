@extends('print.layout')
@section('body')
@php $d = $app->decision; @endphp
<h1>Naskah Perjanjian Hibah Daerah (NPHD)</h1>
<div class="sub">Nomor: {{ $nphd->nphd_no }}<br>tentang <b>Hibah Barang</b> dari Pemerintah Kabupaten Kepahiang kepada {{ $app->applicant->name }}</div>
<p>Pada hari ini tanggal {{ $fmt::date($nphd->nphd_date) }}, yang bertanda tangan di bawah ini: <b>{{ $nphd->signer_name }}</b>, {{ $nphd->signer_position }}, selaku <b>PIHAK KESATU</b>; dan <b>{{ $nphd->recipient_signer_name }}</b> mewakili {{ $app->applicant->name }}, {{ $app->applicant->address }}, selaku <b>PIHAK KEDUA</b>.</p>
<p><b>Pasal 1 — Maksud.</b> PIHAK KESATU memberikan hibah berupa barang untuk kegiatan "{{ $app->title }}", bersumber dari DPA {{ $app->opd->name }} Tahun Anggaran {{ $app->fiscalYear->year }}@if ($d), berdasarkan Keputusan Bupati Nomor {{ $d->decree->decree_no }} tanggal {{ $fmt::date($d->decree->decree_date) }}@endif.</p>
<p><b>Pasal 2 — Barang dan nilai.</b> Nilai hibah <b>{{ $fmt::rupiah($nphd->amount) }}</b> ({{ ucfirst($fmt::terbilang($nphd->amount)) }} rupiah). Daftar rinci, spesifikasi, dan jumlah barang yang diadakan dilampirkan pada Berita Acara Serah Terima (BAST) dan harus sesuai dengan daftar barang pada usulan:</p>
<table class="b"><tr><th>No</th><th>Barang</th><th>Jumlah</th><th>Satuan</th></tr>@foreach ($app->items as $i)<tr><td>{{ $loop->iteration }}</td><td>{{ $i->description }}</td><td class="num">{{ rtrim(rtrim(number_format($i->quantity, 2, ',', '.'), '0'), ',') }}</td><td>{{ $i->unit }}</td></tr>@endforeach</table>
<p><b>Pasal 3 — Pengadaan dan penyerahan.</b> Pengadaan dilaksanakan oleh PIHAK KESATU sesuai ketentuan pengadaan barang/jasa pemerintah, dan diserahkan kepada PIHAK KEDUA dengan Berita Acara Serah Terima (BAST). Barang dicatat keluar dari aset daerah.</p>
<p><b>Pasal 4 — Kewajiban PIHAK KEDUA.</b> Menggunakan barang sesuai peruntukan, tidak mengalihkan kepada pihak lain, dan menyampaikan laporan penggunaan paling lambat 1 (satu) bulan setelah kegiatan selesai atau 10 Januari tahun berikutnya.</p>
<p><b>Pasal 5 — Penutup.</b> Perjanjian dibuat rangkap 2 (dua) bermeterai cukup, masing-masing berkekuatan hukum yang sama.</p>
<table class="ttd"><tr><td><b>PIHAK KEDUA</b><br>{{ $app->applicant->name }}<br><br><br><br><u>{{ $nphd->recipient_signer_name }}</u></td><td><b>PIHAK KESATU</b><br>Kepala {{ $app->opd->name }}<br><br><br><br><u>{{ $nphd->signer_name }}</u></td></tr></table>
@endsection
