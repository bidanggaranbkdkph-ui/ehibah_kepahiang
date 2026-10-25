<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $data['title'] }}</title>
<style>
    @page { margin: 22mm 12mm 16mm 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1B2433; }
    .hdr { position: fixed; top: -17mm; left: 0; right: 0; border-bottom: 2px solid #0B2A5B; padding-bottom: 3px; }
    .hdr b { color: #0B2A5B; font-size: 11px; } .hdr span { color: #047857; font-size: 8px; letter-spacing: 1px; }
    .ftr { position: fixed; bottom: -11mm; left: 0; right: 0; font-size: 7px; color: #5B6678; text-align: center; }
    h1 { font-size: 12px; margin: 0 0 2px; color: #0B2A5B; } .sub { color: #5B6678; margin-bottom: 6px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #0B2A5B; color: #fff; padding: 3px 4px; text-align: left; font-size: 7.5px; }
    td { padding: 2.5px 4px; border-bottom: 1px solid #D5DEEA; vertical-align: top; }
    tr:nth-child(even) td { background: #F5F8FC; }
    .num { text-align: right; white-space: nowrap; }
    tfoot td { font-weight: bold; background: #EAF0F8; border-top: 1.5px solid #0B2A5B; }
</style></head>
<body>
<div class="hdr"><b>E-HIBAH</b> &nbsp; <span>PEMERINTAH KABUPATEN KEPAHIANG · BADAN KEUANGAN DAERAH</span></div>
<div class="ftr">{{ $data['generated'] ?? '' }} · Dokumen dihasilkan sistem E-Hibah</div>
<h1>{{ $data['title'] }}</h1>
<div class="sub">{{ $data['subtitle'] ?? '' }} {{ count($data['rows']) }} baris</div>
<table>
    <thead><tr>@foreach ($data['columns'] as $k => [$label, $type])<th class="{{ in_array($type, ['money', 'int']) ? 'num' : '' }}">{{ $label }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach ($data['rows'] as $row)
        <tr>@foreach ($data['columns'] as $k => [$label, $type])<td class="{{ in_array($type, ['money', 'int']) ? 'num' : '' }}">{{ $svc->formatCell($row[$k] ?? null, $type) }}</td>@endforeach</tr>
    @endforeach
    </tbody>
    @if (! empty($data['totals']) && count($data['rows']))
    <tfoot><tr>@foreach ($data['columns'] as $k => [$label, $type])<td class="{{ in_array($type, ['money', 'int']) ? 'num' : '' }}">@if ($loop->first)JUMLAH @elseif (isset($data['totals'][$k])){{ $svc->formatCell($data['totals'][$k], $type) }}@endif</td>@endforeach</tr></tfoot>
    @endif
</table>
</body></html>
