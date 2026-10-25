@extends('print.layout')
@section('body')
@php $w = $app->warnings->last(); @endphp
<h1>Surat Peringatan Keterlambatan LPJ</h1>
<div class="sub">Nomor: {{ $w->letter_no }}</div>
<table class="t"><tr><td width="70">Kepada</td><td>: Yth. {{ $app->applicant->chair_name }}, Ketua {{ $app->applicant->name }}</td></tr><tr><td>Tanggal</td><td>: {{ $fmt::date($w->letter_date) }}</td></tr><tr><td>Perihal</td><td>: Peringatan ke-{{ $w->level }} — penyampaian laporan pertanggungjawaban hibah</td></tr></table>
<p>Berdasarkan Peraturan Bupati Kepahiang tentang Hibah dan Bantuan Sosial, laporan pertanggungjawaban penggunaan hibah "{{ $app->title }}" ({{ $fmt::rupiah($app->amount_approved) }}) wajib disampaikan paling lambat <b>{{ $fmt::date($w->lpj_due_date) }}</b>. Hingga surat ini diterbitkan, laporan tersebut belum kami terima.</p>
<p>Mohon segera menyampaikan laporan melalui aplikasi E-Hibah. Keterlambatan dicatat sebagai tunggakan dan dapat berakibat pada pertimbangan pemberian hibah berikutnya.</p>
<table class="ttd"><tr><td></td><td>Kepahiang, {{ $fmt::date($w->letter_date) }}<br>Kepala {{ $app->opd->name }}<br><br><br><br>(................................)</td></tr></table>
@endsection
