<section class="alur card">
    <div class="alur-h">
        <span class="alur-ic"><svg class="i i-lg"><use href="#i-coins"/></svg></span>
        <div><h2>Alur Hibah dalam Satu Sistem</h2><p>Proses hibah mulai dari pengajuan hingga monitoring dan evaluasi, lebih mudah, cepat, dan transparan.</p></div>
    </div>
    <div class="steps5">
        @foreach ([
            ['c1', 'i-file', 'Pengajuan', 'Daftarkan lembaga, isi usulan & unggah dokumen wajib.'],
            ['c2', 'i-check', 'Penilaian', 'OPD memeriksa kelengkapan; Tim Verifikasi menilai teknis; OPD merekomendasikan.'],
            ['c3', 'i-gavel', 'Penetapan', 'TAPD mempertimbangkan & mengalokasikan pagu; Bupati menetapkan; NPHD ditandatangani.'],
            ['c4', 'i-wallet', 'Penyaluran', 'Hibah uang melalui SP2D (70% / 30%); hibah barang melalui BAST.'],
            ['c5', 'i-chart', 'Pertanggungjawaban', 'LPJ tepat waktu, sisa dana disetor, monitoring & evaluasi.'],
        ] as $n => [$tone, $icon, $title, $desc])
            <div class="step5 {{ $tone }}">
                <div class="top"><b class="no">{{ $n + 1 }}</b><span class="tile"><svg class="i i-lg"><use href="#{{ $icon }}"/></svg></span></div>
                <h4>{{ $title }}</h4><p>{{ $desc }}</p>
            </div>
        @endforeach
    </div>
</section>
<div class="pillars2">
    @foreach ([
        ['c2', 'i-shield', 'Transparan', 'Setiap tahap tercatat dan dapat ditelusuri.'],
        ['c1', 'i-scale', 'Akuntabel', 'Kewenangan dan tanggung jawab jelas per peran.'],
        ['c5', 'i-target', 'Tepat Sasaran', 'Penerima terverifikasi sesuai persyaratan.'],
        ['c6', 'i-book', 'Tertib Regulasi', 'Perbup Hibah diterjemahkan menjadi kontrol sistem.'],
    ] as [$tone, $icon, $title, $desc])
        <div class="pil2 {{ $tone }}"><span class="tile"><svg class="i i-lg"><use href="#{{ $icon }}"/></svg></span><div><h4>{{ $title }}</h4><p>{{ $desc }}</p></div></div>
    @endforeach
</div>