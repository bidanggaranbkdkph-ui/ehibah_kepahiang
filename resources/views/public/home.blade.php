@extends('layouts.guest')
@section('title', 'Beranda')
@section('wrap', 'lp-main')
@section('hero')
<section class="lp-hero">
    <div class="lp-hero-in">
        <span class="lp-pill">PORTAL RESMI PEMERINTAH KABUPATEN KEPAHIANG</span>
        <h1>Sistem Informasi Hibah<br><em>KABUPATEN KEPAHIANG</em></h1>
        <p>Layanan pengajuan proposal dana hibah secara online untuk lembaga, ormas, dan masyarakat Kabupaten Kepahiang yang transparan, akuntabel, dan terintegrasi.</p>
        <div class="lp-cta">
            <a class="lp-btn gold" href="{{ route('register') }}"><svg class="i i-lg"><use href="#i-edit"/></svg>BUAT PENGAJUAN</a>
            <a class="lp-btn ghost" href="{{ route('public.status') }}"><svg class="i i-lg"><use href="#i-search"/></svg>LACAK PROPOSAL</a>
        </div>
    </div>
</section>
<div class="lp-stats">
    <div><span class="ic"><svg class="i"><use href="#i-wallet"/></svg></span><b>{{ \App\Support\Fmt::rupiahShort($stats['nilai']) }}</b><small>Total Hibah Ditetapkan{{ $fy ? ' TA '.$fy->year : '' }}</small></div>
    <div><span class="ic"><svg class="i"><use href="#i-users"/></svg></span><b>{{ number_format($stats['ditetapkan'], 0, ',', '.') }}</b><small>Penerima Hibah Ditetapkan</small></div>
    <div><span class="ic"><svg class="i"><use href="#i-file"/></svg></span><b>{{ number_format($stats['usulan'], 0, ',', '.') }}</b><small>Proposal Masuk</small></div>
</div>
@endsection
@section('content')
<section class="lp-sec alt" id="dasar-hukum">
    <div class="lp-container">
        <h2 class="lp-title">Dasar Hukum</h2>
        <p class="lp-sub">Regulasi dan dokumen yang menjadi acuan pengelolaan hibah di E-Hibah. Unduh salinannya dari situs resmi yang tersedia.</p>
        @php
            $bpk = fn (string $q) => 'https://peraturan.bpk.go.id/Search?keyword='.urlencode($q);
            $jdih = 'https://jdih.kepahiangkab.go.id/';
            $dasar = [
                ['PP No. 12 Tahun 2019', 'Pengelolaan Keuangan Daerah', 'Prinsip penganggaran, pelaksanaan, penatausahaan, dan pertanggungjawaban.', ['Nasional', 'blue'], [
                    ['Unduh PDF · peraturan.go.id', 'https://peraturan.go.id/files/pp12-2019bt-2019.pdf', true],
                    ['JDIH BPK', $bpk('Peraturan Pemerintah 12 Tahun 2019 Pengelolaan Keuangan Daerah'), false]]],
                ['Permendagri No. 77 Tahun 2020', 'Pedoman Teknis Pengelolaan Keuangan Daerah', 'Acuan teknis pengelolaan hibah APBD, dokumen, dan proses keuangan.', ['Nasional', 'blue'], [
                    ['Unduh PDF · peraturan.go.id', 'https://peraturan.go.id/files/bn1781-2020.pdf', true],
                    ['JDIH BPK', $bpk('Permendagri 77 Tahun 2020 Pedoman Teknis Pengelolaan Keuangan Daerah'), false]]],
                ['Perbup Kepahiang No. 25 Tahun 2025 tentang Hibah', 'Peraturan Bupati tentang Hibah', 'Acuan lokal utama tentang hibah: syarat, tata cara, dan kewenangan.', ['Lokal utama', 'green'], [
                    ['Unduh PDF · JDIH BPK (Perbup No. 25 Tahun 2023)', 'https://peraturan.bpk.go.id/Download/348979/Perbup%20Nomor%2025%20Tahun%202023%20tentang%20Hibah%20dan%20Bantuan%20Sosial%20Salinan.pdf', true],
                    ['JDIH Kepahiang', $jdih, false]]],
                ['Perda Kab. Kepahiang No. 6 Tahun 2021', 'Pengelolaan Keuangan Daerah', 'Pengelolaan keuangan daerah; perlu cek perubahan terbaru.', ['Perlu dicek', 'amber'], [
                    ['JDIH Kepahiang', $jdih, false],
                    ['JDIH BPK', $bpk('Pengelolaan Keuangan Daerah Kepahiang 2021'), false]]],
                ['Perbup No. 28 Tahun 2022', 'Sistem dan Prosedur Pengelolaan Keuangan Daerah', 'Sistem dan prosedur pengelolaan keuangan daerah.', ['Lokal', 'violet'], [
                    ['JDIH BPK', 'https://peraturan.bpk.go.id/Details/269145/perbup-no-28-tahun-2022', false],
                    ['JDIH Kepahiang', $jdih, false]]],
                ['Keputusan Sekda No. 900-43/2023', 'Pembentukan Tim Verifikasi Hibah', 'Dasar pembentukan Tim Verifikasi Hibah.', ['Lokal', 'violet'], [
                    ['JDIH Kepahiang', $jdih, false]]],
                ['Keputusan Sekda No. 900-43/2023', 'Sekretariat Tim Verifikasi', 'Dasar sekretariat Tim Verifikasi.', ['Perlu dicek', 'amber'], [
                    ['JDIH Kepahiang', $jdih, false]]],
                ['Perda APBD & Perkada Penjabaran APBD', 'Dokumen anggaran tahunan', 'Pagu, organisasi, program, dan kegiatan hibah.', ['Tahunan', 'teal'], [
                    ['JDIH Kepahiang', $jdih, false],
                    ['JDIH BPK', $bpk('APBD Kabupaten Kepahiang'), false]]],
                ['Keputusan Bupati', 'Daftar penerima dan besaran hibah', 'Data penerima resmi yang menjadi dasar NPHD dan penyaluran.', ['Tahunan', 'teal'], [
                    ['JDIH Kepahiang', $jdih, false]]],
                ['Format NPHD dan lampiran Perbup', 'Templat dokumen dan formulir', 'Templat dokumen dan formulir di dalam aplikasi.', ['Templat', 'gray'], []],
            ];
        @endphp
        <div class="card pad0"><div class="table-wrap">
            <table class="responsive lp-law">
                <thead><tr><th>No</th><th>Regulasi / Dokumen</th><th>Penerapan dalam E-Hibah</th><th>Sifat</th><th>Unduh / sumber</th></tr></thead>
                <tbody>
                @foreach ($dasar as $i => [$nama, $judul, $fungsi, [$sifat, $warna], $tautan])
                    <tr>
                        <td data-label="No" class="num">{{ $i + 1 }}</td>
                        <td data-label="Regulasi"><b>{{ $nama }}</b><br><span class="small muted">{{ $judul }}</span></td>
                        <td data-label="Penerapan">{{ $fungsi }}</td>
                        <td data-label="Sifat"><span class="badge {{ $warna }}">{{ $sifat }}</span></td>
                        <td data-label="Sumber">
                            @forelse ($tautan as [$label, $url, $pdf])
                                <a class="src-link {{ $pdf ? 'pdf' : '' }}" href="{{ $url }}" target="_blank" rel="noopener noreferrer"><svg class="i"><use href="#{{ $pdf ? 'i-download' : 'i-search' }}"/></svg>{{ $label }}</a>
                            @empty
                                <span class="small muted">Tersedia di dalam aplikasi</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div></div>
        <div class="lp-doc card">
            <span class="doc-ico"><svg class="i i-lg"><use href="#i-book"/></svg></span>
            <div class="doc-txt">
                <small>PEDOMAN PEMBUATAN APLIKASI</small>
                <h3>Blueprint Awal E-Hibah Kabupaten Kepahiang</h3>
                <p>Dokumen perancangan aplikasi: inventarisasi regulasi, arsitektur dan modul, 15 SOP, alur proses bisnis (BPMN), matriks hak akses, rancangan database, workflow status, tahapan implementasi, keamanan, hingga skenario UAT dan kriteria go-live.</p>
                <ul><li>13 bab · 12 halaman</li><li>Versi 1.1 · Oktober 2026</li><li>Format PDF · {{ number_format(filesize(public_path('unduhan/Blueprint_E-Hibah_Kepahiang_v1.1.pdf')) / 1048576, 1, ',', '.') }} MB</li></ul>
            </div>
            <div class="doc-act">
                <a class="lp-btn solid" href="{{ asset('unduhan/Blueprint_E-Hibah_Kepahiang_v1.1.pdf') }}" download><svg class="i i-lg"><use href="#i-download"/></svg>UNDUH BLUEPRINT</a>
                <a class="doc-view" href="{{ asset('unduhan/Blueprint_E-Hibah_Kepahiang_v1.1.pdf') }}" target="_blank" rel="noopener">Buka di tab baru</a>
            </div>
        </div>        <p class="small muted lp-note">Tautan &ldquo;Unduh PDF&rdquo; mengarah ke salinan di situs resmi pemerintah (peraturan.go.id dan JDIH BPK). Dokumen yang tidak memiliki tautan langsung dapat dicari melalui JDIH Kepahiang atau JDIH BPK. Salinan Perbup hibah yang tersedia di JDIH BPK adalah Perbup Kepahiang No. 25 Tahun 2023 (Hibah dan Bantuan Sosial); pastikan nomor dan tahun peraturan yang berlaku pada JDIH Kepahiang.</p>
    </div>
</section>
<section class="lp-sec" id="alur">
    <div class="lp-container">
        <h2 class="lp-title">Alur Pengajuan Cepat &amp; Mudah</h2>
        <p class="lp-sub">Dari pendaftaran hingga pertanggungjawaban, semua tahap dilakukan dalam satu sistem.</p>
        <div class="lp-steps">
            @foreach ([
                ['i-user', 'Registrasi Akun', 'Daftarkan lembaga dan penanggung jawab, lalu tunggu validasi akun oleh admin.'],
                ['i-upload', 'Unggah Proposal', 'Isi usulan, rencana anggaran, dan unggah dokumen persyaratan.'],
                ['i-check', 'Verifikasi OPD', 'OPD memeriksa kelengkapan, Tim Verifikasi menilai teknis, lalu TAPD dan Bupati menetapkan.'],
                ['i-wallet', 'Pencairan Dana', 'Penandatanganan NPHD, penyaluran hibah, dan penyampaian laporan pertanggungjawaban.'],
            ] as $n => [$icon, $title, $desc])
                <div class="lp-step">
                    <small>{{ sprintf('%02d', $n + 1) }}</small>
                    <span class="ring"><svg class="i i-lg"><use href="#{{ $icon }}"/></svg></span>
                    <h4>{{ ($n + 1) }}. {{ $title }}</h4>
                    <p>{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="lp-sec alt" id="syarat">
    <div class="lp-container">
        <h2 class="lp-title">Syarat &amp; Ketentuan</h2>
        <p class="lp-sub">Siapkan dokumen berikut sebelum mengajukan proposal hibah.</p>
        <div class="lp-two">
            <div class="card">
                <h3><svg class="i"><use href="#i-file"/></svg> Dokumen persyaratan pengajuan</h3>
                <ul class="lp-check">
                    @forelse ($syarat as $s)<li>{{ $s }}</li>@empty<li>Daftar dokumen ditampilkan setelah data referensi tersedia.</li>@endforelse
                </ul>
                <p class="small muted">Dokumen tambahan dapat diminta sesuai jenis penerima (lembaga, ormas, partai politik, instansi pemerintah, BUMN/BUMD).</p>
            </div>
            <div class="card">
                <h3><svg class="i"><use href="#i-scale"/></svg> Ketentuan penting</h3>
                <ul class="lp-check">
                    <li>Pemohon wajib mendaftar dan akunnya divalidasi sebelum dapat mengajukan usulan.</li>
                    <li>Hibah uang disalurkan dua tahap: {{ \App\Support\Setting::int('disbursement.stage1_percent') }}% lalu {{ 100 - \App\Support\Setting::int('disbursement.stage1_percent') }}%; hibah barang melalui berita acara serah terima.</li>
                    <li>Laporan pertanggungjawaban (LPJ) disampaikan paling lambat {{ \App\Support\Setting::int('lpj.months_after_activity') }} bulan setelah kegiatan selesai.</li>
                    <li>Sisa dana wajib disetor ke kas daerah.</li>
                    <li>Seluruh aktivitas penting tercatat pada audit trail dan dapat ditelusuri.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="lp-sec" id="transparansi">
    <div class="lp-container">
        <h2 class="lp-title">Transparansi &amp; Akuntabilitas</h2>
        <p class="lp-sub">Ringkasan hibah {{ $fy ? 'Tahun Anggaran '.$fy->year : '' }} — hanya data agregat, tanpa data pribadi.</p>
        <div class="grid g4 kpis">
            <div class="card kpi2"><span class="tile c1"><svg class="i i-lg"><use href="#i-file"/></svg></span><div><small>Usulan masuk</small><b>{{ number_format($stats['usulan'], 0, ',', '.') }}</b><span class="up">seluruh OPD</span></div></div>
            <div class="card kpi2"><span class="tile c2"><svg class="i i-lg"><use href="#i-check"/></svg></span><div><small>Usulan ditetapkan</small><b>{{ number_format($stats['ditetapkan'], 0, ',', '.') }}</b><span class="up">penerima hibah</span></div></div>
            <div class="card kpi2"><span class="tile c3"><svg class="i i-lg"><use href="#i-wallet"/></svg></span><div><small>Nilai ditetapkan</small><b>{{ \App\Support\Fmt::rupiahShort($stats['nilai']) }}</b><span class="up">APBD {{ $fy?->year }}</span></div></div>
            <div class="card kpi2"><span class="tile c4"><svg class="i i-lg"><use href="#i-shield"/></svg></span><div><small>Hibah selesai</small><b>{{ $stats['selesai'] }}</b><span class="up">LPJ diterima</span></div></div>
        </div>
        <div class="pillars2">
            @foreach ([['c2', 'i-shield', 'Transparan', 'Setiap tahap tercatat dan dapat ditelusuri.'], ['c1', 'i-scale', 'Akuntabel', 'Kewenangan dan tanggung jawab jelas per peran.'], ['c5', 'i-target', 'Tepat Sasaran', 'Penerima terverifikasi sesuai persyaratan.'], ['c6', 'i-book', 'Tertib Regulasi', 'Perbup Hibah diterjemahkan menjadi kontrol sistem.']] as [$tone, $icon, $title, $desc])
                <div class="pil2 {{ $tone }}"><span class="tile"><svg class="i i-lg"><use href="#{{ $icon }}"/></svg></span><div><h4>{{ $title }}</h4><p>{{ $desc }}</p></div></div>
            @endforeach
        </div>
    </div>
</section>

<section class="lp-sec alt" id="kontak">
    <div class="lp-container">
        <h2 class="lp-title">Kontak</h2>
        <p class="lp-sub">Butuh bantuan pendaftaran atau pengajuan? Hubungi admin E-Hibah.</p>
        <div class="lp-contact">
            <div class="card"><span class="tile c1"><svg class="i i-lg"><use href="#i-mail"/></svg></span><div><small>Email Admin</small><a href="mailto:wiradiansyahwira@gmail.com">wiradiansyahwira@gmail.com</a></div></div>
            <div class="card"><span class="tile c2"><svg class="i i-lg"><use href="#i-phone"/></svg></span><div><small>WhatsApp Admin</small><a href="https://wa.me/6282281661514" target="_blank" rel="noopener">0822-8166-1514</a></div></div>
            <div class="card"><span class="tile c3"><svg class="i i-lg"><use href="#i-db"/></svg></span><div><small>Pengelola</small><b>Badan Keuangan Daerah</b><span class="small muted">Pemerintah Kabupaten Kepahiang</span></div></div>
        </div>
    </div>
</section>
@endsection