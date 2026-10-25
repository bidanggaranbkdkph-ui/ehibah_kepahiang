<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\GoodsDelivery;
use App\Models\GrantApplication;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Jalur hibah BARANG (Perbup Pasal 13-14, 17): pengadaan -> BAST -> pencatatan keluar aset.
 * Tidak ada SP2D; bukti penyaluran = BAST + daftar barang.
 */
class GoodsService
{
    public function __construct(private AuditService $audit, private DocumentChecklist $docs, private WorkflowService $workflow)
    {
    }

    public function save(GrantApplication $app, User $user, array $data, array $items): GoodsDelivery
    {
        if ($app->isMoney()) {
            throw ValidationException::withMessages(['workflow' => 'Jalur penyerahan barang hanya untuk hibah barang.']);
        }
        if (! $user->hasRole('OPD') || $user->organization_id !== $app->opd_id) {
            throw new AuthorizationException('Hanya OPD pemilik urusan yang mengelola penyerahan barang.');
        }
        if ($app->status !== Status::NPHD_SIGNED) {
            throw ValidationException::withMessages(['workflow' => 'Penyerahan barang dimulai setelah NPHD ditandatangani.']);
        }
        $x = Validator::make($data, [
            'procurement_ref' => 'required|string|max:120', 'procurement_date' => 'required|date',
            'bast_no' => 'required|string|max:80', 'bast_date' => 'required|date',
            'received_by_name' => 'required|string|max:150', 'notes' => 'nullable|string',
        ], [], [
            'procurement_ref' => 'Referensi dokumen pengadaan', 'procurement_date' => 'Tanggal pengadaan',
            'bast_no' => 'Nomor BAST', 'bast_date' => 'Tanggal BAST', 'received_by_name' => 'Penerima barang',
        ])->validate();

        $clean = collect($items)->filter(fn ($i) => filled($i['description'] ?? null))->values();
        if ($clean->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Daftar barang yang diserahkan wajib diisi.']);
        }

        return DB::transaction(function () use ($app, $user, $x, $clean) {
            $d = GoodsDelivery::updateOrCreate(['application_id' => $app->id], $x + ['created_by' => $user->id, 'status' => 'DRAFT']);
            $d->items()->delete();
            foreach ($clean as $i) {
                $d->items()->create([
                    'description' => $i['description'], 'spec' => $i['spec'] ?? null,
                    'quantity' => (float) ($i['quantity'] ?? 1), 'unit' => $i['unit'] ?? 'unit', 'unit_value' => (int) ($i['unit_value'] ?? 0),
                ]);
            }
            $this->audit->record('GOODS_DELIVERY_SAVED', $d, null, ['bast_no' => $d->bast_no, 'items' => $clean->count()], 'PROSES', $app->id, $user);

            return $d;
        });
    }

    /** OPD menyatakan barang diserahkan. Wajib BAST (unggahan) + pakta integritas C.2. */
    public function markDelivered(GrantApplication $app, User $user): GoodsDelivery
    {
        $d = $app->goodsDelivery;
        if (! $d || $d->items()->count() === 0) {
            throw ValidationException::withMessages(['workflow' => 'Data penyerahan/daftar barang belum disusun.']);
        }
        $missing = $this->docs->missing($app, 'PENYERAHAN');
        if ($missing) {
            throw ValidationException::withMessages(['workflow' => array_map(fn ($m) => "Dokumen penyerahan belum lengkap: $m.", $missing)]);
        }
        $d->update(['status' => 'DISERAHKAN', 'delivered_at' => now()]);
        $this->audit->record('GOODS_DELIVERED', $d, null, ['bast_no' => $d->bast_no], 'PROSES', $app->id, $user);

        return $d;
    }

    /** BPKAD mencatat barang keluar dari aset daerah, lalu usulan berstatus Disalurkan. */
    public function recordAsset(GrantApplication $app, User $user, string $assetRef): GoodsDelivery
    {
        if (! $user->hasRole('BPKAD')) {
            throw new AuthorizationException('Hanya BPKAD/pengelola barang yang mencatat aset.');
        }
        $d = $app->goodsDelivery;
        if (! $d || $d->status !== 'DISERAHKAN') {
            throw ValidationException::withMessages(['workflow' => 'Penyerahan belum selesai (BAST belum lengkap) — aset tidak dapat dicatat.']);
        }
        if (blank($assetRef)) {
            throw ValidationException::withMessages(['asset_ref' => 'Referensi pencatatan aset wajib diisi.']);
        }
        $d->update(['status' => 'DICATAT', 'asset_ref' => $assetRef, 'asset_recorded_at' => now(), 'asset_recorded_by' => $user->id]);
        $this->audit->record('GOODS_ASSET_RECORDED', $d, null, ['asset_ref' => $assetRef], 'PROSES', $app->id, $user);
        if ($app->fresh()->status === Status::NPHD_SIGNED) {
            $this->workflow->system($app, 'mark_disbursed', $user);
        }

        return $d;
    }
}
