@extends('print.layout')
@section('body')
<h1>Surat Pernyataan Tanggung Jawab Penerima Hibah</h1>
<div class="sub">Lampiran D</div>
<p>Yang bertanda tangan di bawah ini, {{ $app->applicant->chair_name }}, Ketua {{ $app->applicant->name }}, menyatakan bahwa hibah sebesar <b>{{ $fmt::rupiah($app->amount_approved ?? $app->amount_requested) }}</b> dari Pemerintah Kabupaten Kepahiang (NPHD {{ $app->activeNphd()?->nphd_no ?? '........' }}) akan saya gunakan sesuai peruntukan dan menjadi tanggung jawab formil dan materiil saya.</p>
<p>Dokumen asli bukti pengeluaran disimpan oleh penerima hibah dan salinannya disampaikan kepada SKPD melalui aplikasi E-Hibah.</p>
<table class="ttd"><tr><td></td><td>Kepahiang, ........................ {{ now()->year }}<br>Penerima hibah,<br><br>(meterai)<br><br><u>{{ $app->applicant->chair_name }}</u></td></tr></table>
@endsection
