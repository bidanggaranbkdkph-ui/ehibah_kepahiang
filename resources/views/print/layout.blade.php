<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $title }}</title>
<style>
    @page { margin: 20mm 20mm 18mm 22mm; }
    body { font-family: DejaVu Serif, serif; font-size: 11px; line-height: 1.5; color: #000; }
    .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 6px; margin-bottom: 14px; }
    .kop b { font-size: 13px; display: block; } .kop span { font-size: 10px; }
    h1 { text-align: center; font-size: 13px; margin: 0 0 2px; text-transform: uppercase; }
    .sub { text-align: center; margin-bottom: 12px; }
    table.t { width: 100%; border-collapse: collapse; } table.t td { vertical-align: top; padding: 1.5px 3px; }
    table.b { width: 100%; border-collapse: collapse; margin: 6px 0; } table.b th, table.b td { border: 1px solid #000; padding: 3px 5px; } table.b th { background: #eee; }
    .num { text-align: right; } .ttd { margin-top: 24px; width: 100%; } .ttd td { text-align: center; vertical-align: top; width: 50%; }
    .draft { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: center; font-size: 8px; color: #888; font-family: DejaVu Sans, sans-serif; }
    p { text-align: justify; margin: 4px 0; } ol, ul { margin: 3px 0 3px 18px; padding: 0; }
</style></head>
<body>
<div class="kop"><b>PEMERINTAH KABUPATEN KEPAHIANG</b><b>{{ strtoupper($app->opd->name) }}</b><span>{{ $app->opd->address ?? 'Kepahiang, Provinsi Bengkulu' }}</span></div>
@yield('body')
<div class="draft">Dokumen dihasilkan sistem E-Hibah · No. register {{ $app->reg_number ?? '-' }} · Templat kerja — sesuaikan dengan format lampiran resmi Peraturan Bupati (validasi Bagian Hukum).</div>
</body></html>
