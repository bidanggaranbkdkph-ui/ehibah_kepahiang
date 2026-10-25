<?php

namespace App\Services;

use App\Models\BudgetAllocation;
use App\Models\BudgetCeiling;
use App\Models\GrantApplication;
use App\Models\User;
use App\Support\Fmt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pagu hibah per OPD & alokasi per usulan. Penguncian baris pagu mencegah
 * dua alokasi paralel melampaui pagu (race condition).
 */
class BudgetService
{
    public function __construct(private AuditService $audit)
    {
    }

    public function ceilingFor(GrantApplication $app): ?BudgetCeiling
    {
        return BudgetCeiling::query()
            ->where('fiscal_year_id', $app->fiscal_year_id)->where('opd_id', $app->opd_id)->first();
    }

    /** Cadangkan (RESERVED) dana pagu untuk usulan. Idempoten: memperbarui alokasi bila sudah ada. */
    public function reserve(GrantApplication $app, User $user, int $amount): BudgetAllocation
    {
        return DB::transaction(function () use ($app, $user, $amount) {
            $ceiling = BudgetCeiling::query()
                ->where('fiscal_year_id', $app->fiscal_year_id)->where('opd_id', $app->opd_id)
                ->lockForUpdate()->first();

            if (! $ceiling) {
                throw ValidationException::withMessages(['amount' => 'Pagu hibah untuk OPD ini pada tahun anggaran tersebut belum ditetapkan.']);
            }
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Nilai alokasi harus lebih dari nol.']);
            }

            $existing = BudgetAllocation::query()->where('application_id', $app->id)->first();
            $others = (int) BudgetAllocation::query()->where('ceiling_id', $ceiling->id)
                ->whereIn('status', ['RESERVED', 'CONFIRMED'])
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->sum('amount');

            if ($others + $amount > $ceiling->ceiling) {
                throw ValidationException::withMessages(['amount' => sprintf(
                    'Alokasi %s melebihi sisa pagu %s (pagu %s, terpakai %s).',
                    Fmt::rupiah($amount), Fmt::rupiah($ceiling->ceiling - $others), Fmt::rupiah($ceiling->ceiling), Fmt::rupiah($others)
                )]);
            }

            $old = $existing?->only(['amount', 'status']);
            $alloc = BudgetAllocation::updateOrCreate(
                ['application_id' => $app->id],
                [
                    'ceiling_id' => $ceiling->id, 'account_code' => $ceiling->account_code, 'dpa_ref' => $ceiling->dpa_ref,
                    'amount' => $amount, 'status' => 'RESERVED', 'allocated_by' => $user->id,
                    'synced_at' => now(), 'released_at' => null, 'release_reason' => null,
                ]
            );
            $this->audit->record('BUDGET_RESERVED', $alloc, $old, ['amount' => $amount, 'ceiling_id' => $ceiling->id], 'PROSES', $app->id, $user);

            return $alloc;
        });
    }

    public function confirm(GrantApplication $app, User $user, string $accountCode, string $dpaRef): BudgetAllocation
    {
        $alloc = $app->allocation;
        $alloc->update([
            'status' => 'CONFIRMED', 'account_code' => $accountCode, 'dpa_ref' => $dpaRef,
            'confirmed_by' => $user->id, 'confirmed_at' => now(), 'synced_at' => now(),
        ]);
        $this->audit->record('BUDGET_CONFIRMED', $alloc, null, ['account_code' => $accountCode, 'dpa_ref' => $dpaRef], 'PROSES', $app->id, $user);

        return $alloc;
    }

    public function adjust(GrantApplication $app, User $user, int $amount): void
    {
        $alloc = $app->allocation;
        if ($alloc && $alloc->amount !== $amount) {
            $old = $alloc->amount;
            $alloc->update(['amount' => $amount]);
            $this->audit->record('BUDGET_ADJUSTED', $alloc, ['amount' => $old], ['amount' => $amount], 'PROSES', $app->id, $user);
        }
    }

    /** Kembalikan dana ke pagu (usulan ditolak / dibatalkan). */
    public function release(GrantApplication $app, ?User $user, string $reason): void
    {
        $alloc = BudgetAllocation::query()->where('application_id', $app->id)->whereIn('status', ['RESERVED', 'CONFIRMED'])->first();
        if ($alloc) {
            $alloc->update(['status' => 'RELEASED', 'released_at' => now(), 'release_reason' => $reason]);
            $this->audit->record('BUDGET_RELEASED', $alloc, ['status' => 'RESERVED/CONFIRMED'], ['reason' => $reason], 'PROSES', $app->id, $user);
        }
    }
}
