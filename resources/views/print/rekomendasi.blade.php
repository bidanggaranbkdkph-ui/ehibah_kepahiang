@extends('print.layout')
@section('body')
@php $r = $app->recommendation; @endphp
<h1>Rekomendasi Calon Penerima Hibah</h1>
<div class="sub">Nomor: {{ $r->letter_no }}</div>
<table class="t"><tr><td width="70">Kepada</td><td>: Yth. Bupati Kepahiang c.q. Ketua Tim Anggaran Pemerintah Daerah (TAPD)</td></tr>
    <tr><td>Dari</td><td>: Kepala {{ $app->opd->name }}</td></tr><tr><td>Tanggal</td><td>: {{ $fmt::date($r->letter_date) }}</td></tr>
    <tr><td>Perihal</td><td>: Rekomendasi calon penerima hibah Tahun Anggaran {{ $app->fiscalYear->year }}</td></tr></table>
<p>Berdasarkan hasil verifikasi administrasi dan verifikasi teknis atas usulan yang diajukan, dengan ini kami merekomendasikan calon penerima hibah berikut:</p>
<table class="b">
    <tr><th width="30">No</th><th>Uraian</th><th>Keterangan</th></tr>
    <tr><td>1</td><td>Nama calon penerima</td><td>{{ $app->applicant->name }}</td></tr>
    <tr><td>2</td><td>Alamat</td><td>{{ $app->applicant->address }}</td></tr>
    <tr><td>3</td><td>Kategori penerima</td><td>{{ $app->category->name }}</td></tr>
    <tr><td>4</td><td>Bentuk hibah</td><td>{{ $app->grantType->name }}</td></tr>
    <tr><td>5</td><td>Peruntukan / kegiatan</td><td>{{ $app->title }} — {{ $app->program_activity }}</td></tr>
    <tr><td>6</td><td>Nilai usulan</td><td>{{ $fmt::rupiah($app->amount_requested) }}</td></tr>
    <tr><td>7</td><td><b>Besaran hibah yang direkomendasikan</b></td><td><b>{{ $fmt::rupiah($r->amount_recommended) }}</b><br><i>({{ ucfirst($fmt::terbilang($r->amount_recommended)) }} rupiah)</i></td></tr>
    <tr><td>8</td><td>Hasil verifikasi teknis</td><td>@php $t = $app->verifications->where('stage', 'TEKNIS')->last(); @endphp {{ $t ? 'Skor '.$t->score.' — '.($t->result === 'LULUS' ? 'layak' : 'tidak layak') : '-' }}</td></tr>
</table>
<p>Demikian rekomendasi ini disampaikan untuk dipertimbangkan sebagai bahan penetapan penerima hibah.</p>
<table class="ttd"><tr><td></td><td>Kepahiang, {{ $fmt::date($r->letter_date) }}<br>Kepala {{ $app->opd->name }}<br><br><br><br><u>{{ $r->issuer->name }}</u></td></tr></table>
@endsection
