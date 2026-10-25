<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\AuditLog;
use App\Models\Decree;
use App\Models\GrantApplication;
use App\Models\User;
use App\Support\Fmt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sepuluh laporan Blueprint Bab 9.2 + daftar nominatif. Setiap laporan menghasilkan
 * struktur yang sama: ['title','columns'=>[key=>[label,type]],'rows'=>[],'totals'=>[]],
 * sehingga tampilan HTML, Excel dan PDF selalu memuat angka yang identik.
 * Cakupan baris mengikuti hak akses pengguna (visibleTo).
 */
class ReportService
{
    public const CATALOG = [
        'register-usulan' => ['Register seluruh usulan', 'Per tahun anggaran'],
        'verifikasi-rekomendasi' => ['Rekap hasil verifikasi & rekomendasi', 'Administrasi, teknis, nomor & nilai rekomendasi'],
        'penerima-nilai' => ['Daftar penerima & nilai hibah', 'Penerima yang telah ditetapkan'],
        'nphd' => ['Rekap NPHD & status penandatanganan', 'Nomor, nilai, penandatangan'],
        'rekap-opd-jenis' => ['Rekap per OPD & jenis hibah', 'Usulan, penetapan, penyaluran'],
        'penyaluran' => ['Rekap penyaluran uang & penyerahan barang', 'SP2D, RKUD, BAST, aset'],
        'lpj' => ['Daftar LPJ, keterlambatan & hasil pemeriksaan', 'Tenggat, status, hari terlambat'],
        'sisa-dana' => ['Rekap sisa dana & pengembalian', 'Tersalur, realisasi, sisa, setoran kas daerah'],
        'monev' => ['Laporan monitoring, temuan & tindak lanjut', 'Termasuk bukti distribusi Bupati/Inspektorat'],
        'audit' => ['Riwayat perubahan data & akses (audit)', 'Hanya peran pengawas'],
    ];

    public function build(string $slug, User $user, Request $request): array
    {
        abort_unless(isset(self::CATALOG[$slug]), 404);
        $method = 'r'.str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
        $data = $this->$method($user, $request);
        $data['title'] = self::CATALOG[$slug][0];
        $data['slug'] = $slug;
        $data['generated'] = Fmt::datetime(now()).' oleh '.$user->name;

        return $data;
    }

    private function apps(User $user, Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return GrantApplication::query()->visibleTo($user)->where('status', '!=', Status::DRAFT->value)
            ->with(['applicant', 'opd', 'grantType', 'category', 'fiscalYear'])
            ->when($request->filled('fy'), fn ($q) => $q->where('fiscal_year_id', $request->input('fy')))
            ->when($request->filled('opd'), fn ($q) => $q->where('opd_id', $request->input('opd')))
            ->when($request->filled('type'), fn ($q) => $q->where('grant_type_id', $request->input('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('reg_number');
    }

    // 1 ----------------------------------------------------------------
    private function rRegisterUsulan(User $u, Request $r): array
    {
        $rows = $this->apps($u, $r)->get()->map(fn ($a, $i) => [
            'no' => $i + 1, 'reg' => $a->reg_number, 'tgl' => $a->submitted_at, 'pemohon' => $a->applicant->name, 'judul' => $a->title,
            'opd' => $a->opd->name, 'jenis' => $a->grantType->name, 'nilai' => $a->amount_requested, 'status' => $a->status->label(),
        ])->all();

        return [
            'columns' => ['no' => ['No', 'int'], 'reg' => ['No. Register', 'text'], 'tgl' => ['Tgl Diajukan', 'date'], 'pemohon' => ['Pemohon', 'text'],
                'judul' => ['Judul Usulan', 'text'], 'opd' => ['OPD', 'text'], 'jenis' => ['Jenis', 'text'], 'nilai' => ['Nilai Usulan (Rp)', 'money'], 'status' => ['Status', 'text']],
            'rows' => $rows, 'totals' => ['nilai' => array_sum(array_column($rows, 'nilai'))],
        ];
    }

    // 2 ----------------------------------------------------------------
    private function rVerifikasiRekomendasi(User $u, Request $r): array
    {
        $rows = $this->apps($u, $r)->with(['verifications', 'recommendation'])->get()->map(function ($a, $i) {
            $adm = $a->verifications->where('stage', 'ADMINISTRASI')->last();
            $tek = $a->verifications->where('stage', 'TEKNIS')->last();

            return [
                'no' => $i + 1, 'reg' => $a->reg_number, 'pemohon' => $a->applicant->name, 'opd' => $a->opd->name,
                'adm' => $adm?->result ?? '-', 'skor' => $tek?->score, 'teknis' => $tek ? ($tek->result === 'LULUS' ? 'Layak' : 'Tidak layak') : '-',
                'no_rek' => $a->recommendation?->letter_no ?? '-', 'tgl_rek' => $a->recommendation?->letter_date,
                'usulan' => $a->amount_requested, 'rek' => $a->recommendation?->amount_recommended ?? 0,
            ];
        })->all();

        return [
            'columns' => ['no' => ['No', 'int'], 'reg' => ['No. Register', 'text'], 'pemohon' => ['Pemohon', 'text'], 'opd' => ['OPD', 'text'],
                'adm' => ['Hasil Adm.', 'text'], 'skor' => ['Skor Teknis', 'int'], 'teknis' => ['Hasil Teknis', 'text'], 'no_rek' => ['No. Rekomendasi', 'text'],
                'tgl_rek' => ['Tgl Rekomendasi', 'date'], 'usulan' => ['Nilai Usulan (Rp)', 'money'], 'rek' => ['Nilai Rekomendasi (Rp)', 'money']],
            'rows' => $rows, 'totals' => ['usulan' => array_sum(array_column($rows, 'usulan')), 'rek' => array_sum(array_column($rows, 'rek'))],
        ];
    }

    // 3 ----------------------------------------------------------------
    private function rPenerimaNilai(User $u, Request $r): array
    {
        $rows = $this->apps($u, $r)->whereNotNull('amount_approved')->with('decision.decree')->get()->map(fn ($a, $i) => [
            'no' => $i + 1, 'pemohon' => $a->applicant->name, 'alamat' => $a->applicant->address, 'kategori' => $a->category->name,
            'jenis' => $a->grantType->name, 'opd' => $a->opd->name, 'sk' => $a->decision?->decree?->decree_no ?? '-',
            'tgl_sk' => $a->decision?->decree?->decree_date, 'nilai' => $a->amount_approved,
        ])->all();

        return [
            'columns' => ['no' => ['No', 'int'], 'pemohon' => ['Nama Penerima', 'text'], 'alamat' => ['Alamat', 'text'], 'kategori' => ['Kategori', 'text'],
                'jenis' => ['Bentuk', 'text'], 'opd' => ['OPD', 'text'], 'sk' => ['No. Keputusan Bupati', 'text'], 'tgl_sk' => ['Tgl Keputusan', 'date'], 'nilai' => ['Nilai Ditetapkan (Rp)', 'money']],
            'rows' => $rows, 'totals' => ['nilai' => array_sum(array_column($rows, 'nilai'))],
        ];
    }

    // 4 ----------------------------------------------------------------
    private function rNphd(User $u, Request $r): array
    {
        $rows = $this->apps($u, $r)->with('nphds')->get()->flatMap(fn ($a) => $a->nphds->where('status', '!=', 'BATAL')->map(fn ($n) => [
            'reg' => $a->reg_number, 'pemohon' => $a->applicant->name, 'nphd' => $n->nphd_no, 'tgl' => $n->nphd_date, 'nilai' => $n->amount,
            'ttd' => $n->signer_name, 'status' => $n->status === 'DITANDATANGANI' ? 'Ditandatangani' : 'Draf', 'tgl_ttd' => $n->signed_at,
            'metode' => $n->signature_method ?? '-',
        ]))->values()->map(fn ($row, $i) => ['no' => $i + 1] + $row)->all();

        return [
            'columns' => ['no' => ['No', 'int'], 'reg' => ['No. Register', 'text'], 'pemohon' => ['Penerima', 'text'], 'nphd' => ['No. NPHD', 'text'], 'tgl' => ['Tgl NPHD', 'date'],
                'nilai' => ['Nilai (Rp)', 'money'], 'ttd' => ['Penandatangan (Kepala SKPD)', 'text'], 'status' => ['Status', 'text'], 'tgl_ttd' => ['Tgl TTD', 'date'], 'metode' => ['Metode', 'text']],
            'rows' => $rows, 'totals' => ['nilai' => array_sum(array_column($rows, 'nilai'))],
        ];
    }

    // 5 ----------------------------------------------------------------
    private function rRekapOpdJenis(User $u, Request $r): array
    {
        $groups = $this->apps($u, $r)->with('disbursements')->get()->groupBy(fn ($a) => $a->opd->name.'|'.$a->grantType->name);
        $rows = [];
        $i = 0;
        foreach ($groups->sortKeys() as $key => $apps) {
            [$opd, $jenis] = explode('|', $key);
            $rows[] = [
                'no' => ++$i, 'opd' => $opd, 'jenis' => $jenis, 'usulan' => $apps->count(),
                'nilai_usulan' => $apps->sum('amount_requested'), 'ditetapkan' => $apps->whereNotNull('amount_approved')->count(),
                'nilai_tetap' => $apps->sum('amount_approved'),
                'tersalur' => $apps->sum(fn ($a) => $a->disbursements->where('status', 'DISALURKAN')->sum('amount')),
                'selesai' => $apps->where('status', Status::CLOSED)->count(),
            ];
        }

        return [
            'columns' => ['no' => ['No', 'int'], 'opd' => ['OPD', 'text'], 'jenis' => ['Jenis Hibah', 'text'], 'usulan' => ['Jml Usulan', 'int'], 'nilai_usulan' => ['Nilai Usulan (Rp)', 'money'],
                'ditetapkan' => ['Jml Ditetapkan', 'int'], 'nilai_tetap' => ['Nilai Ditetapkan (Rp)', 'money'], 'tersalur' => ['Tersalur (Rp)', 'money'], 'selesai' => ['Selesai', 'int']],
            'rows' => $rows, 'totals' => collect(['usulan', 'nilai_usulan', 'ditetapkan', 'nilai_tetap', 'tersalur', 'selesai'])->mapWithKeys(fn ($k) => [$k => array_sum(array_column($rows, $k))])->all(),
        ];
    }

    // 6 ----------------------------------------------------------------
    private function rPenyaluran(User $u, Request $r): array
    {
        $rows = [];
        foreach ($this->apps($u, $r)->with(['disbursements', 'goodsDelivery.items'])->get() as $a) {
            foreach ($a->disbursements->where('status', 'DISALURKAN') as $d) {
                $rows[] = ['reg' => $a->reg_number, 'pemohon' => $a->applicant->name, 'jalur' => 'Uang — tahap '.$d->stage.' ('.rtrim(rtrim(number_format($d->percentage, 2), '0'), '.').'%)',
                    'nilai' => $d->amount, 'dok' => 'SP2D '.$d->sp2d_no.' / SPM '.$d->spm_no, 'bukti' => $d->rkud_ref, 'tgl' => $d->paid_at];
            }
            if ($a->goodsDelivery && in_array($a->goodsDelivery->status, ['DISERAHKAN', 'DICATAT'], true)) {
                $g = $a->goodsDelivery;
                $rows[] = ['reg' => $a->reg_number, 'pemohon' => $a->applicant->name, 'jalur' => 'Barang — '.$g->items->count().' jenis',
                    'nilai' => $g->items->sum(fn ($i) => $i->quantity * $i->unit_value), 'dok' => 'BAST '.$g->bast_no, 'bukti' => $g->asset_ref ?? 'belum dicatat sbg aset', 'tgl' => $g->delivered_at];
            }
        }
        $rows = collect($rows)->values()->map(fn ($row, $i) => ['no' => $i + 1] + $row)->all();

        return [
            'columns' => ['no' => ['No', 'int'], 'reg' => ['No. Register', 'text'], 'pemohon' => ['Penerima', 'text'], 'jalur' => ['Jalur / Tahap', 'text'], 'nilai' => ['Nilai (Rp)', 'money'],
                'dok' => ['Dokumen Dasar', 'text'], 'bukti' => ['Bukti (RKUD / Aset)', 'text'], 'tgl' => ['Tanggal', 'date']],
            'rows' => $rows, 'totals' => ['nilai' => array_sum(array_column($rows, 'nilai'))],
        ];
    }

    // 7 ----------------------------------------------------------------
    private function rLpj(User $u, Request $r): array
    {
        $calc = new GrantCalculator;
        $rows = $this->apps($u, $r)->whereIn('status', [Status::DISBURSED->value, Status::LPJ_REVIEW->value, Status::CLOSED->value])
            ->with('reports')->get()->map(function ($a, $i) use ($calc) {
                $rep = $a->reports->where('report_type', 'AKHIR')->last();
                $late = $rep?->submitted_at && $a->lpj_due_date ? max(0, (int) $a->lpj_due_date->diffInDays($rep->submitted_at->startOfDay(), false)) : $calc->daysLate($a);

                return [
                    'no' => $i + 1, 'reg' => $a->reg_number, 'pemohon' => $a->applicant->name, 'opd' => $a->opd->name, 'tenggat' => $a->lpj_due_date,
                    'status' => $rep ? ['DRAFT' => 'Draf', 'DIAJUKAN' => 'Diajukan', 'PERLU_PERBAIKAN' => 'Perlu perbaikan', 'DITERIMA' => 'Diterima'][$rep->review_status] : 'Belum ada',
                    'tgl' => $rep?->submitted_at, 'telat' => $late, 'tunggak' => ($a->lpj_overdue ? 'Ya' : 'Tidak'), 'sp' => $a->warnings()->count(),
                ];
            })->all();

        return [
            'columns' => ['no' => ['No', 'int'], 'reg' => ['No. Register', 'text'], 'pemohon' => ['Penerima', 'text'], 'opd' => ['OPD', 'text'], 'tenggat' => ['Tenggat LPJ', 'date'],
                'status' => ['Status LPJ Akhir', 'text'], 'tgl' => ['Tgl Diajukan', 'date'], 'telat' => ['Terlambat (hari)', 'int'], 'tunggak' => ['Tunggakan', 'text'], 'sp' => ['Surat Peringatan', 'int']],
            'rows' => $rows, 'totals' => ['sp' => array_sum(array_column($rows, 'sp'))],
        ];
    }

    // 8 ----------------------------------------------------------------
    private function rSisaDana(User $u, Request $r): array
    {
        $rows = $this->apps($u, $r)->whereHas('grantType', fn ($q) => $q->where('form', 'UANG'))->whereIn('status', [Status::DISBURSED->value, Status::LPJ_REVIEW->value, Status::CLOSED->value])
            ->with(['disbursements', 'reports', 'fundReturns'])->get()->map(function ($a, $i) {
                $tersalur = $a->disbursements->where('status', 'DISALURKAN')->sum('amount');
                $rep = $a->reports->where('report_type', 'AKHIR')->last();
                $real = $rep?->total_realization ?? 0;
                $sisa = $rep ? max(0, $tersalur - $real) : 0;
                $setor = $a->fundReturns->sum('amount');

                return ['no' => $i + 1, 'reg' => $a->reg_number, 'pemohon' => $a->applicant->name, 'tersalur' => $tersalur, 'real' => $real, 'sisa' => $sisa,
                    'setor' => $setor, 'selisih' => $sisa - $setor, 'ref' => $a->fundReturns->pluck('kas_ref')->implode(', ') ?: '-'];
            })->all();

        return [
            'columns' => ['no' => ['No', 'int'], 'reg' => ['No. Register', 'text'], 'pemohon' => ['Penerima', 'text'], 'tersalur' => ['Dana Tersalur (Rp)', 'money'], 'real' => ['Realisasi (Rp)', 'money'],
                'sisa' => ['Sisa Dana (Rp)', 'money'], 'setor' => ['Disetor ke Kas Daerah (Rp)', 'money'], 'selisih' => ['Belum Disetor (Rp)', 'money'], 'ref' => ['Ref. Setoran', 'text']],
            'rows' => $rows, 'totals' => collect(['tersalur', 'real', 'sisa', 'setor', 'selisih'])->mapWithKeys(fn ($k) => [$k => array_sum(array_column($rows, $k))])->all(),
        ];
    }

    // 9 ----------------------------------------------------------------
    private function rMonev(User $u, Request $r): array
    {
        $ids = $this->apps($u, $r)->select('grant_applications.id');
        $rows = \App\Models\MonitoringRecord::with(['application.applicant', 'officer'])->whereIn('application_id', $ids)->orderByDesc('visited_at')->get()->map(fn ($m, $i) => [
            'no' => $i + 1, 'reg' => $m->application->reg_number, 'pemohon' => $m->application->applicant->name, 'tgl' => $m->visited_at, 'petugas' => $m->officer->name,
            'jenis' => $m->type === 'MONEV' ? 'Monitoring' : 'Evaluasi', 'temuan' => $m->findings, 'rek' => $m->recommendation ?? '-', 'tl' => $m->follow_up ?? '-',
            'status' => $m->follow_up_status === 'OPEN' ? 'Terbuka' : 'Selesai', 'dist' => ($m->sent_to_bupati_at ? 'Bupati' : '').($m->sent_to_inspektorat_at ? ' + Inspektorat (tembusan)' : ''),
        ])->all();

        return [
            'columns' => ['no' => ['No', 'int'], 'reg' => ['No. Register', 'text'], 'pemohon' => ['Penerima', 'text'], 'tgl' => ['Tanggal', 'date'], 'petugas' => ['Petugas', 'text'], 'jenis' => ['Jenis', 'text'],
                'temuan' => ['Temuan', 'text'], 'rek' => ['Rekomendasi', 'text'], 'tl' => ['Tindak Lanjut', 'text'], 'status' => ['Status TL', 'text'], 'dist' => ['Distribusi', 'text']],
            'rows' => $rows, 'totals' => [],
        ];
    }

    // 10 ---------------------------------------------------------------
    private function rAudit(User $u, Request $r): array
    {
        abort_unless(\App\Support\Permission::allows($u, 'audit_trail', 'V'), 403);
        $rows = AuditLog::query()
            ->when($r->filled('fy'), fn ($q) => $q->whereYear('created_at', \App\Models\FiscalYear::find($r->input('fy'))?->year))
            ->orderByDesc('id')->limit(5000)->get()->map(fn ($l, $i) => [
                'no' => $i + 1, 'id' => $l->id, 'waktu' => $l->created_at, 'kat' => $l->category, 'user' => $l->user_name ?? '-', 'peran' => $l->role_code ?? '-',
                'aksi' => $l->action, 'obj' => trim(($l->entity_type ?? '').' #'.($l->entity_id ?? '')), 'lama' => $l->old_values ?? '', 'baru' => $l->new_values ?? '', 'ip' => $l->ip ?? '-',
            ])->all();

        return [
            'columns' => ['no' => ['No', 'int'], 'id' => ['ID Log', 'int'], 'waktu' => ['Waktu', 'datetime'], 'kat' => ['Kategori', 'text'], 'user' => ['Pengguna', 'text'], 'peran' => ['Peran', 'text'],
                'aksi' => ['Aksi', 'text'], 'obj' => ['Objek', 'text'], 'lama' => ['Nilai Lama', 'text'], 'baru' => ['Nilai Baru', 'text'], 'ip' => ['IP', 'text']],
            'rows' => $rows, 'totals' => [], 'note' => 'Maksimum 5.000 baris terbaru. Verifikasi keutuhan rantai hash di menu Audit Trail.',
        ];
    }

    // nominatif --------------------------------------------------------
    public function nominatif(Decree $decree): array
    {
        $rows = $decree->decisions()->with(['application.applicant', 'application.opd', 'application.grantType', 'application.category'])->get()
            ->map(fn ($d, $i) => [
                'no' => $i + 1, 'nama' => $d->application->applicant->name, 'alamat' => $d->application->applicant->address,
                'kategori' => $d->application->category->name, 'bentuk' => $d->application->grantType->name,
                'opd' => $d->application->opd->name, 'nilai' => $d->amount_approved,
            ])->all();

        return [
            'title' => 'Daftar Nominatif Penerima Hibah', 'slug' => 'nominatif',
            'subtitle' => 'Lampiran Keputusan Bupati No. '.$decree->decree_no.' tanggal '.Fmt::date($decree->decree_date).' — '.$decree->perda_ref.' / '.$decree->perkada_ref,
            'columns' => ['no' => ['No', 'int'], 'nama' => ['Nama Penerima', 'text'], 'alamat' => ['Alamat', 'text'], 'kategori' => ['Kategori', 'text'], 'bentuk' => ['Bentuk', 'text'], 'opd' => ['OPD', 'text'], 'nilai' => ['Nilai (Rp)', 'money']],
            'rows' => $rows, 'totals' => ['nilai' => array_sum(array_column($rows, 'nilai'))], 'generated' => Fmt::datetime(now()),
        ];
    }

    // ------------------------------------------------------------------ keluaran
    public function formatCell(mixed $v, string $type): string
    {
        if ($v === null || $v === '') {
            return '-';
        }

        return match ($type) {
            'money' => Fmt::rupiah($v, false),
            'date' => Fmt::date($v),
            'datetime' => Fmt::datetime($v),
            'int' => (string) $v,
            default => (string) $v,
        };
    }

    public function excel(array $data, string $filename): StreamedResponse
    {
        $sheet = new Spreadsheet;
        $ws = $sheet->getActiveSheet()->setTitle(mb_substr($data['title'], 0, 30));
        $cols = array_values($data['columns']);
        $keys = array_keys($data['columns']);
        $last = Coordinate::stringFromColumnIndex(count($keys));

        $ws->setCellValue('A1', 'PEMERINTAH KABUPATEN KEPAHIANG — E-HIBAH');
        $ws->setCellValue('A2', mb_strtoupper($data['title']));
        $ws->setCellValue('A3', ($data['subtitle'] ?? '').' Dicetak: '.($data['generated'] ?? ''));
        foreach (['A1', 'A2'] as $c) {
            $ws->getStyle($c)->getFont()->setBold(true)->setSize($c === 'A1' ? 11 : 13);
        }
        $ws->getStyle('A3')->getFont()->setItalic(true)->setSize(9);

        $hr = 5;
        foreach ($keys as $ci => $k) {
            $ws->setCellValue([$ci + 1, $hr], $cols[$ci][0]);
        }
        $ws->getStyle("A$hr:$last$hr")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B2A5B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $row = $hr + 1;
        foreach ($data['rows'] as $rec) {
            foreach ($keys as $ci => $k) {
                $type = $cols[$ci][1];
                $v = $rec[$k] ?? null;
                if (in_array($type, ['date', 'datetime'], true) && $v) {
                    $v = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($v instanceof \DateTimeInterface ? $v : new \DateTime((string) $v));
                    $ws->setCellValue([$ci + 1, $row], $v);
                    $ws->getStyle([$ci + 1, $row])->getNumberFormat()->setFormatCode($type === 'date' ? 'dd mmm yyyy' : 'dd mmm yyyy hh:mm');
                } elseif ($type === 'money') {
                    $ws->setCellValue([$ci + 1, $row], (float) ($v ?? 0));
                    $ws->getStyle([$ci + 1, $row])->getNumberFormat()->setFormatCode('#,##0');
                } else {
                    // string eksplisit: mencegah injeksi rumus (=, +, -, @) dari data pengguna
                    $ws->setCellValueExplicit([$ci + 1, $row], (string) ($v ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
            $row++;
        }
        if (! empty($data['totals'])) {
            foreach ($keys as $ci => $k) {
                if (isset($data['totals'][$k])) {
                    $ws->setCellValue([$ci + 1, $row], (float) $data['totals'][$k]);
                    $ws->getStyle([$ci + 1, $row])->getNumberFormat()->setFormatCode('#,##0');
                }
            }
            $ws->setCellValue([1, $row], 'JUMLAH');
            $ws->getStyle("A$row:$last$row")->getFont()->setBold(true);
            $ws->getStyle("A$row:$last$row")->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }
        $ws->getStyle("A$hr:$last".max($row, $hr))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR);
        foreach (range(1, count($keys)) as $ci) {
            $ws->getColumnDimensionByColumn($ci)->setAutoSize(true);
        }
        $ws->freezePane('A'.($hr + 1));
        $ws->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageSetup()->setFitToPage(true);

        return response()->streamDownload(function () use ($sheet) {
            (new Xlsx($sheet))->save('php://output');
        }, $filename.'-'.now()->format('Ymd').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function pdf(array $data, ?string $title = null)
    {
        $svc = $this;

        return Pdf::loadView('reports.pdf', ['data' => $data, 'svc' => $svc])->setPaper('a4', 'landscape')
            ->download(str($title ?? $data['title'])->slug().'-'.now()->format('Ymd').'.pdf');
    }
}
