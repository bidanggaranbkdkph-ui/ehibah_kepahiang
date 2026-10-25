@extends('print.layout')
@section('body')
@php $d = $app->decision; $plan = (new \App\Services\GrantCalculator)->stagePlan((int) $nphd->amount, $app->disbursement_scheme); @endphp
<h1>Naskah Perjanjian Hibah Daerah (NPHD)</h1>
<div class="sub">Nomor: {{ $nphd->nphd_no }}<br>tentang <b>Hibah Uang</b> dari Pemerintah Kabupaten Kepahiang kepada {{ $app->applicant->name }}</div>
<p>Pada hari ini tanggal {{ $fmt::date($nphd->nphd_date) }}, yang bertanda tangan di bawah ini:</p>
<table class="t">
    <tr><td width="14">1.</td><td width="80">Nama</td><td>: {{ $nphd->signer_name }}</td></tr><tr><td></td><td>Jabatan</td><td>: {{ $nphd->signer_position }}</td></tr>
    <tr><td></td><td colspan="2">selaku Kepala SKPD yang berwenang, bertindak untuk dan atas nama Pemerintah Kabupaten Kepahiang, selanjutnya disebut <b>PIHAK KESATU</b>.</td></tr>
    <tr><td>2.</td><td>Nama</td><td>: {{ $nphd->recipient_signer_name }}</td></tr><tr><td></td><td>Lembaga</td><td>: {{ $app->applicant->name }}</td></tr>
    <tr><td></td><td>Alamat</td><td>: {{ $app->applicant->address }}</td></tr><tr><td></td><td colspan="2">bertindak untuk dan atas nama lembaga tersebut, selanjutnya disebut <b>PIHAK KEDUA</b>.</td></tr>
</table>
<p>PIHAK KESATU dan PIHAK KEDUA sepakat mengadakan perjanjian hibah dengan ketentuan sebagai berikut:</p>
<p><b>Pasal 1 — Maksud.</b> PIHAK KESATU memberikan hibah uang kepada PIHAK KEDUA untuk kegiatan "{{ $app->title }}" sesuai usulan, bersumber dari APBD Kabupaten Kepahiang Tahun Anggaran {{ $app->fiscalYear->year }}@if ($d), berdasarkan Keputusan Bupati Nomor {{ $d->decree->decree_no }} tanggal {{ $fmt::date($d->decree->decree_date) }}@endif.</p>
<p><b>Pasal 2 — Nilai hibah.</b> Besaran hibah sebesar <b>{{ $fmt::rupiah($nphd->amount) }}</b> ({{ ucfirst($fmt::terbilang($nphd->amount)) }} rupiah).</p>
<p><b>Pasal 3 — Penyaluran.</b> Hibah disalurkan melalui mekanisme LS ke rekening PIHAK KEDUA ({{ $app->applicant->bank_name }} {{ $app->applicant->bank_account_no }} a.n. {{ $app->applicant->bank_account_name }}), dengan tahapan:</p>
<table class="b"><tr><th>Tahap</th><th>Persentase</th><th>Nilai</th><th>Syarat</th></tr>
@foreach ($plan as $p)<tr><td>{{ $p['stage'] }}</td><td>{{ rtrim(rtrim(number_format($p['percentage'], 2), '0'), '.') }}%</td><td class="num">{{ $fmt::rupiah($p['amount']) }}</td><td>{{ $p['stage'] === 1 ? 'NPHD, pakta integritas, dan dokumen pencairan lengkap' : 'Laporan penggunaan tahap I diterima' }}</td></tr>@endforeach</table>
<p><b>Pasal 4 — Kewajiban PIHAK KEDUA.</b> (1) Menggunakan hibah sesuai peruntukan dan RAB. (2) Bertanggung jawab formil dan materiil atas penggunaan hibah. (3) Menyampaikan laporan pertanggungjawaban paling lambat 1 (satu) bulan setelah kegiatan selesai atau 10 Januari tahun berikutnya. (4) Menyetor sisa hibah ke Kas Daerah dan menyampaikan bukti setor kepada SKPD dengan tembusan BUD.</p>
<p><b>Pasal 5 — Monitoring dan evaluasi.</b> PIHAK KESATU berwenang melakukan monitoring dan evaluasi; hasilnya disampaikan kepada Bupati dengan tembusan Inspektorat Daerah.</p>
<p><b>Pasal 6 — Penutup.</b> Perjanjian ini dibuat rangkap 2 (dua) bermeterai cukup, masing-masing mempunyai kekuatan hukum yang sama.</p>
<table class="ttd"><tr><td><b>PIHAK KEDUA</b><br>{{ $app->applicant->name }}<br><br><br><br><u>{{ $nphd->recipient_signer_name }}</u></td><td><b>PIHAK KESATU</b><br>Kepala {{ $app->opd->name }}<br><br><br><br><u>{{ $nphd->signer_name }}</u></td></tr></table>
@endsection
