<div class="stack">
    <div class="card">
        <div class="card-h"><h3>Data usulan</h3></div>
        <dl class="def">
            <dt>Pemohon</dt><dd>{{ $app->applicant->name }}<div class="small muted">{{ $app->applicant->address }}</div></dd>
            <dt>Kategori penerima</dt><dd>{{ $app->category->name }}</dd>
            <dt>OPD pemilik urusan</dt><dd>{{ $app->opd->name }}</dd>
            <dt>Tahun anggaran</dt><dd>{{ $app->fiscalYear->year }}</dd>
            <dt>Dasar hukum</dt><dd>{{ $app->regulation?->title }} @if ($app->regulation && ! $app->regulation->isActive())<span class="badge red">{{ $app->regulation->status }}</span>@endif</dd>
            <dt>Lokasi</dt><dd>{{ $app->location }}</dd>
            <dt>Pelaksanaan</dt><dd>{{ tgl($app->activity_start) }} s.d. {{ tgl($app->activity_end) }}</dd>
            <dt>Diajukan</dt><dd>{{ tgljam($app->submitted_at) }}</dd>
            <dt>Hibah berulang</dt><dd>{{ $app->is_recurring ? 'Ya — '.$app->recurring_reason : 'Tidak' }}</dd>
            <dt>Pernyataan non-ganda</dt><dd>{{ $app->funding_declaration ? 'Dinyatakan' : 'Belum' }}</dd>
        </dl>
    </div>
    <div class="card">
        <div class="card-h"><h3>Uraian</h3></div>
        <dl class="def" style="grid-template-columns:150px 1fr">
            <dt>Latar belakang</dt><dd>{!! nl2br(e($app->background)) !!}</dd>
            <dt>Maksud & tujuan</dt><dd>{!! nl2br(e($app->purpose)) !!}</dd>
            <dt>Sasaran</dt><dd>{!! nl2br(e($app->target)) !!}</dd>
            <dt>Program/kegiatan</dt><dd>{!! nl2br(e($app->program_activity)) !!}</dd>
        </dl>
    </div>
    <div class="card pad0">
        <div class="card-h" style="padding:1.1rem 1.1rem 0"><h3>{{ $app->isMoney() ? 'Rencana Anggaran Biaya' : 'Daftar barang yang diusulkan' }}</h3></div>
        <div class="table-wrap"><table>
            <thead><tr><th>No</th><th>Uraian</th><th class="num">Volume</th><th>Satuan</th><th class="num">Harga satuan</th><th class="num">Jumlah</th></tr></thead>
            <tbody>@foreach ($app->items as $i)<tr><td>{{ $loop->iteration }}</td><td>{{ $i->description }}</td><td class="num">{{ rtrim(rtrim(number_format($i->quantity, 2, ',', '.'), '0'), ',') }}</td><td>{{ $i->unit }}</td><td class="num">{{ rupiah($i->unit_price, false) }}</td><td class="num">{{ rupiah($i->subtotal, false) }}</td></tr>@endforeach</tbody>
            <tfoot><tr><td colspan="5">Total</td><td class="num">{{ rupiah($app->amount_requested) }}</td></tr></tfoot>
        </table></div>
    </div>
    <div class="card">
        <div class="card-h"><h3>Susunan panitia</h3></div>
        <div class="table-wrap"><table class="table-plain"><thead><tr><th>Nama</th><th>Jabatan</th></tr></thead><tbody>@foreach ($app->committee ?? [] as $c)<tr><td>{{ $c['nama'] }}</td><td>{{ $c['jabatan'] }}</td></tr>@endforeach</tbody></table></div>
    </div>
</div>
