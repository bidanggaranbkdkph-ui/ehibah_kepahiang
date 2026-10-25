<?php

namespace App\Services;

use App\Models\ApplicationDocument;
use App\Models\DocumentRequirement;
use App\Models\GrantApplication;
use Illuminate\Support\Collection;

/**
 * Menentukan dokumen wajib per tahap (kondisional menurut bentuk hibah & kategori penerima)
 * dan status kelengkapannya. Dipakai oleh guard workflow dan halaman unggah.
 */
class DocumentChecklist
{
    public const STAGES = [
        'PENGAJUAN' => 'Pengajuan usulan',
        'NPHD' => 'NPHD',
        'PENCAIRAN' => 'Pencairan hibah uang',
        'PENYERAHAN' => 'Penyerahan hibah barang',
        'LPJ' => 'Pertanggungjawaban (LPJ)',
        'ARSIP' => 'Arsip & penutupan',
    ];

    public function requirementsFor(GrantApplication $app, string $stage): Collection
    {
        $form = $app->grantType->form;

        return DocumentRequirement::query()
            ->where('is_active', true)
            ->where('stage', $stage)
            ->where(fn ($q) => $q->whereNull('grant_form')->orWhere('grant_form', $form))
            ->where(fn ($q) => $q->whereNull('recipient_category_id')->orWhere('recipient_category_id', $app->recipient_category_id))
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    /**
     * @param  array{0:string,1:int}|null  $related  mis. ['report', 12] untuk dokumen LPJ tertentu
     * @return Collection<int, array{requirement:DocumentRequirement, document:?ApplicationDocument, state:string}>
     */
    public function items(GrantApplication $app, string $stage, ?array $related = null): Collection
    {
        $docs = ApplicationDocument::query()
            ->where('application_id', $app->id)->where('stage', $stage)->where('is_current', true)
            ->when($related, fn ($q) => $q->where('related_type', $related[0])->where('related_id', $related[1]),
                fn ($q) => $q->whereNull('related_type'))
            ->get()->keyBy('doc_type');

        return $this->requirementsFor($app, $stage)->map(function (DocumentRequirement $r) use ($docs) {
            $doc = $docs->get($r->code);

            return ['requirement' => $r, 'document' => $doc, 'state' => $this->state($doc)];
        });
    }

    public function state(?ApplicationDocument $doc): string
    {
        if (! $doc) {
            return 'MISSING';
        }
        if ($doc->isExpired()) {
            return 'EXPIRED';
        }

        return match ($doc->review_status) {
            'SESUAI' => 'SESUAI',
            'TIDAK_SESUAI' => 'TIDAK_SESUAI',
            default => 'UPLOADED',
        };
    }

    /** Nama dokumen wajib yang belum diunggah / kedaluwarsa. */
    public function missing(GrantApplication $app, string $stage, ?array $related = null): array
    {
        return $this->items($app, $stage, $related)
            ->filter(fn ($i) => $i['requirement']->is_mandatory && in_array($i['state'], ['MISSING', 'EXPIRED'], true))
            ->map(fn ($i) => $i['requirement']->name.($i['state'] === 'EXPIRED' ? ' (kedaluwarsa)' : ''))
            ->values()->all();
    }

    /** Dokumen wajib yang belum dinyatakan SESUAI oleh pemeriksa. */
    public function notVerified(GrantApplication $app, string $stage, ?array $related = null): array
    {
        return $this->items($app, $stage, $related)
            ->filter(fn ($i) => $i['requirement']->is_mandatory && $i['state'] !== 'SESUAI')
            ->map(fn ($i) => $i['requirement']->name)
            ->values()->all();
    }

    public function progress(GrantApplication $app, string $stage, ?array $related = null): array
    {
        $items = $this->items($app, $stage, $related)->where(fn ($i) => $i['requirement']->is_mandatory);
        $total = $items->count();
        $done = $items->filter(fn ($i) => ! in_array($i['state'], ['MISSING', 'EXPIRED'], true))->count();

        return ['done' => $done, 'total' => $total, 'percent' => $total ? (int) round($done / $total * 100) : 100];
    }
}
