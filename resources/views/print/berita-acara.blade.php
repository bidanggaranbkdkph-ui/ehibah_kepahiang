@extends('print.layout')
@section('body')
<h1>Berita Acara Verifikasi Usulan Hibah</h1>
<div class="sub">No. register {{ $app->reg_number }}</div>
<p>Telah dilakukan verifikasi atas usulan hibah "{{ $app->title }}" oleh {{ $app->applicant->name }} (nilai usulan {{ $fmt::rupiah($app->amount_requested) }}) dengan hasil sebagai berikut:</p>
<table class="b"><tr><th>Tahap</th><th>Putaran</th><th>Hasil</th><th>Pemeriksa</th><th>Tanggal</th><th>Catatan</th></tr>
@foreach ($app->verifications as $v)
<tr><td>{{ $v->stage === 'ADMINISTRASI' ? 'Administrasi' : 'Teknis' }}</td><td>{{ $v->round }}</td><td>{{ ['LULUS' => 'Lulus', 'DIKEMBALIKAN' => 'Dikembalikan', 'DITOLAK' => 'Ditolak/tidak layak'][$v->result] }}{{ $v->score !== null ? ' (skor '.$v->score.')' : '' }}</td><td>{{ $v->verifier->name }}</td><td>{{ $fmt::date($v->verified_at) }}</td><td>{{ $v->notes }}</td></tr>
@endforeach</table>
<table class="ttd"><tr><td></td><td>Kepahiang, {{ $fmt::date(now()) }}<br>Tim Verifikasi / OPD<br><br><br><br>(................................)</td></tr></table>
@endsection
