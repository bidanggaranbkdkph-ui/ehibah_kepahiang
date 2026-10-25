@extends('print.layout')
@section('body')
<h1>Pakta Integritas Penerima Hibah {{ $app->isMoney() ? 'Uang' : 'Barang' }}</h1>
<div class="sub">Lampiran {{ $app->isMoney() ? 'C.1' : 'C.2' }}</div>
<p>Saya yang bertanda tangan di bawah ini:</p>
<table class="t"><tr><td width="90">Nama</td><td>: {{ $app->applicant->chair_name }}</td></tr><tr><td>Jabatan</td><td>: Ketua {{ $app->applicant->name }}</td></tr><tr><td>Alamat</td><td>: {{ $app->applicant->address }}</td></tr></table>
<p>selaku penerima hibah {{ $app->isMoney() ? 'uang' : 'barang' }} sebesar {{ $fmt::rupiah($app->amount_approved ?? $app->amount_requested) }} untuk kegiatan "{{ $app->title }}", menyatakan dengan sebenarnya bahwa saya:</p>
<ol>
    <li>akan menggunakan hibah sesuai dengan peruntukan sebagaimana tercantum dalam NPHD;</li>
    <li>bertanggung jawab secara formil dan materiil atas penggunaan hibah;</li>
    <li>tidak menerima pendanaan ganda atas kegiatan yang sama dari APBN/sumber lain;</li>
    <li>akan menyampaikan laporan pertanggungjawaban tepat waktu{{ $app->isMoney() ? ' dan menyetor sisa dana ke Kas Daerah bila ada' : '' }};</li>
    <li>bersedia dikenai sanksi sesuai ketentuan peraturan perundang-undangan apabila melanggar pernyataan ini.</li>
</ol>
<table class="ttd"><tr><td></td><td>Kepahiang, ........................ {{ now()->year }}<br>Yang membuat pernyataan,<br><br>(meterai)<br><br><u>{{ $app->applicant->chair_name }}</u></td></tr></table>
@endsection
