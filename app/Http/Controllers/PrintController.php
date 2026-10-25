<?php

namespace App\Http\Controllers;

use App\Models\GrantApplication;
use App\Services\AuditService;
use App\Support\Fmt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

/**
 * Cetak dokumen resmi dari data permohonan (nilai & nama selalu identik dengan data sistem).
 * Format di sini adalah templat kerja; wajib disetarakan dengan lampiran resmi Perbup oleh Bagian Hukum.
 */
class PrintController extends Controller
{
    private const TEMPLATES = [
        'rekomendasi' => ['Lampiran A — Rekomendasi calon penerima hibah', ['OPD', 'TAPD', 'BUPATI', 'VERIFIKATOR', 'INSPEKTORAT', 'BPKAD']],
        'nphd' => ['Lampiran B — Naskah Perjanjian Hibah Daerah (NPHD)', ['OPD', 'PEMOHON', 'BPKAD', 'BUPATI', 'INSPEKTORAT']],
        'pakta' => ['Lampiran C — Pakta integritas penerima hibah', ['OPD', 'PEMOHON', 'BPKAD', 'INSPEKTORAT']],
        'tanggung-jawab' => ['Lampiran D — Surat pernyataan tanggung jawab', ['OPD', 'PEMOHON', 'BPKAD', 'INSPEKTORAT']],
        'berita-acara' => ['Berita acara verifikasi', ['OPD', 'VERIFIKATOR', 'TAPD', 'INSPEKTORAT']],
        'peringatan' => ['Surat peringatan keterlambatan LPJ', ['OPD', 'PEMOHON', 'INSPEKTORAT']],
    ];

    public function show(Request $request, GrantApplication $application, string $template, AuditService $audit)
    {
        abort_unless(isset(self::TEMPLATES[$template]), 404);
        $this->authorizeView($application);
        if (! $request->user()->hasRole(...self::TEMPLATES[$template][1])) {
            throw new AuthorizationException('Dokumen ini tidak tersedia untuk peran Anda.');
        }
        $app = $application->load(['applicant', 'opd', 'grantType', 'category', 'fiscalYear', 'items', 'recommendation', 'decision.decree', 'nphds', 'verifications.verifier', 'tapdReview', 'warnings', 'regulation']);

        $missing = match ($template) {
            'rekomendasi' => $app->recommendation ? null : 'Rekomendasi belum diterbitkan.',
            'nphd' => $app->activeNphd() ? null : 'NPHD belum disusun.',
            'peringatan' => $app->warnings->isNotEmpty() ? null : 'Belum ada surat peringatan.',
            'berita-acara' => $app->verifications->isNotEmpty() ? null : 'Belum ada hasil verifikasi.',
            default => null,
        };
        if ($missing) {
            return back()->with('error', $missing);
        }

        $audit->access('DOCUMENT_PRINTED', $request->user(), ['application_id' => $app->id, 'template' => $template]);
        $view = $template === 'nphd' ? ($app->isMoney() ? 'print.nphd-uang' : 'print.nphd-barang') : 'print.'.$template;

        return Pdf::loadView($view, [
            'app' => $app, 'nphd' => $app->activeNphd(), 'fmt' => Fmt::class, 'title' => self::TEMPLATES[$template][0],
        ])->setPaper('a4')->stream($template.'-'.($app->reg_number ? str_replace('/', '-', $app->reg_number) : $app->id).'.pdf');
    }
}
