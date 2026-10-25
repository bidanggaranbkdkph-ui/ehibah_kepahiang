<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\GrantApplication;
use App\Models\NphdDocument;
use App\Models\TapdReview;
use App\Models\User;
use App\Models\Verification;
use App\Support\Fmt;
use App\Support\Setting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Pencatatan data proses yang bukan perpindahan status:
 * penilaian teknis, pertimbangan TAPD + alokasi pagu, penyusunan NPHD.
 */
class ProcessService
{
    public function __construct(private AuditService $audit, private BudgetService $budget)
    {
    }

    /** Verifikasi teknis & penilaian (Tim Verifikasi). Skor rata-rata >= ambang => LULUS. */
    public function recordTechnical(GrantApplication $app, User $user, array $scores, ?string $notes): Verification
    {
        $this->require($app->status === Status::TECH_CHECK, 'Penilaian teknis hanya pada status Verifikasi Teknis.');
        if (! $user->hasRole('VERIFIKATOR')) {
            throw new AuthorizationException('Hanya Tim Verifikasi yang dapat menilai teknis.');
        }
        $rules = [];
        foreach (WorkflowService::TECH_SCORES as $k => $label) {
            $rules[$k] = 'required|integer|between:0,100';
        }
        $x = Validator::make($scores, $rules, [], WorkflowService::TECH_SCORES)->validate();
        $avg = (int) round(array_sum($x) / count($x));
        $result = $avg >= Setting::int('tech.min_score') ? 'LULUS' : 'DITOLAK';

        return DB::transaction(function () use ($app, $user, $x, $avg, $result, $notes) {
            $v = Verification::create([
                'application_id' => $app->id, 'stage' => 'TEKNIS', 'round' => $app->revision_no, 'result' => $result,
                'checklist' => ['scores' => $x], 'score' => $avg, 'notes' => $notes,
                'verifier_id' => $user->id, 'verified_at' => now(),
            ]);
            $this->audit->record('TECH_ASSESSED', $v, null, ['score' => $avg, 'result' => $result], 'PROSES', $app->id, $user);

            return $v;
        });
    }

    /** Pertimbangan TAPD + pencadangan dana dari pagu OPD (blokir bila pagu tidak cukup). */
    public function recordTapdReview(GrantApplication $app, User $user, int $amount, ?string $notes): TapdReview
    {
        $this->require($app->status === Status::TAPD_REVIEW, 'Pertimbangan TAPD hanya pada status Pertimbangan TAPD.');
        if (! $user->hasRole('TAPD')) {
            throw new AuthorizationException('Hanya TAPD yang dapat mencatat pertimbangan.');
        }
        if ($amount < 1 || $amount > (int) $app->amount_recommended) {
            throw ValidationException::withMessages(['amount' => 'Nilai pertimbangan harus antara Rp 1 dan nilai rekomendasi ('.Fmt::rupiah($app->amount_recommended).').']);
        }

        return DB::transaction(function () use ($app, $user, $amount, $notes) {
            $this->budget->reserve($app, $user, $amount);
            $r = TapdReview::updateOrCreate(['application_id' => $app->id], [
                'amount_considered' => $amount, 'notes' => $notes, 'reviewer_id' => $user->id, 'reviewed_at' => now(),
            ]);
            $this->audit->record('TAPD_REVIEWED', $r, null, ['amount' => $amount], 'PROSES', $app->id, $user);

            return $r;
        });
    }

    /** Menyusun/ulang NPHD (versi baru membatalkan draf sebelumnya). */
    public function draftNphd(GrantApplication $app, User $user, array $data): NphdDocument
    {
        $this->require($app->status === Status::BUDGETED, 'NPHD disusun setelah usulan dianggarkan (status Dianggarkan).');
        if (! $user->hasRole('OPD') || $user->organization_id !== $app->opd_id) {
            throw new AuthorizationException('Hanya OPD pemilik urusan yang menyusun NPHD.');
        }
        $x = Validator::make($data, [
            'nphd_no' => 'required|string|max:80',
            'nphd_date' => 'required|date',
            'signer_user_id' => 'required|exists:users,id',
            'recipient_signer_name' => 'required|string|max:150',
        ], [], [
            'nphd_no' => 'Nomor NPHD', 'nphd_date' => 'Tanggal NPHD',
            'signer_user_id' => 'Penandatangan (Kepala SKPD)', 'recipient_signer_name' => 'Penandatangan penerima',
        ])->validate();

        $signer = User::findOrFail($x['signer_user_id']);
        if ($signer->organization_id !== $app->opd_id || ! $signer->hasRole('OPD') || ! preg_match('/^kepala/i', (string) $signer->position)) {
            throw ValidationException::withMessages(['signer_user_id' => 'Penandatangan NPHD harus Kepala SKPD yang berwenang atas urusan ini (Pasal 8).']);
        }

        return DB::transaction(function () use ($app, $user, $x, $signer) {
            $prev = $app->nphds()->get();
            foreach ($prev->where('status', 'DRAFT') as $old) {
                $old->update(['status' => 'BATAL']);
            }
            $n = NphdDocument::create([
                'application_id' => $app->id, 'nphd_no' => $x['nphd_no'], 'nphd_date' => $x['nphd_date'],
                'amount' => $app->amount_approved, 'version' => ($prev->max('version') ?? 0) + 1, 'status' => 'DRAFT',
                'signer_user_id' => $signer->id, 'signer_name' => $signer->name, 'signer_position' => $signer->position,
                'recipient_signer_name' => $x['recipient_signer_name'], 'created_by' => $user->id,
            ]);
            $this->audit->record('NPHD_DRAFTED', $n, null, ['no' => $n->nphd_no, 'amount' => $n->amount, 'version' => $n->version], 'PROSES', $app->id, $user);

            return $n;
        });
    }

    private function require(bool $ok, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['workflow' => $message]);
        }
    }
}
