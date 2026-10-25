<?php

namespace App\Services;

use App\Models\DataCorrection;
use App\Models\Decree;
use App\Models\Disbursement;
use App\Models\GrantApplication;
use App\Models\NphdDocument;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Koreksi data pasca-kunci. Prinsip:
 *  - hanya bidang whitelist (config ehibah.corrections); nilai uang TIDAK dapat dikoreksi di sini
 *    (batalkan dan ajukan ulang agar pagu, NPHD dan penyaluran tetap konsisten),
 *  - alasan wajib; penyetuju harus orang lain (prinsip 4-mata) dengan peran yang ditentukan,
 *  - nilai lama & baru tercatat di koreksi dan audit trail; usulan tidak dapat diubah diam-diam.
 */
class CorrectionService
{
    private const DATE_FIELDS = ['activity_start', 'activity_end', 'nphd_date', 'sp2d_date', 'decree_date'];

    public function __construct(private AuditService $audit, private NotificationService $notifier)
    {
    }

    public static function modelFor(string $entity): string
    {
        return match ($entity) {
            'application' => GrantApplication::class,
            'nphd' => NphdDocument::class,
            'disbursement' => Disbursement::class,
            'decree' => Decree::class,
            default => throw ValidationException::withMessages(['entity' => 'Entitas tidak dikenal.']),
        };
    }

    /** Daftar bidang yang dapat dikoreksi: [entity => [field => approverRoles]] */
    public static function fields(): array
    {
        return config('ehibah.corrections');
    }

    public function request(GrantApplication $app, User $user, string $entity, int $entityId, string $field, ?string $newValue, string $reason): DataCorrection
    {
        if (! Permission::allows($user, 'koreksi_data', 'P')) {
            throw new AuthorizationException('Anda tidak berwenang mengajukan koreksi data.');
        }
        if (! GrantApplication::query()->visibleTo($user)->whereKey($app->id)->exists()) {
            throw new AuthorizationException('Usulan di luar cakupan Anda.');
        }
        if (! isset(self::fields()[$entity][$field])) {
            throw ValidationException::withMessages(['field' => 'Bidang ini tidak dapat dikoreksi melalui jalur koreksi (nilai uang: batalkan & ajukan ulang).']);
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan koreksi wajib diisi (minimal 10 karakter).']);
        }
        $model = $this->resolve($app, $entity, $entityId);
        $old = $this->stringify($model->getAttribute($field));
        $new = trim((string) $newValue);
        $this->validateValue($model, $field, $new);

        if ($old === $new) {
            throw ValidationException::withMessages(['new_value' => 'Nilai baru sama dengan nilai saat ini.']);
        }
        if ($app->corrections()->where('entity', $entity)->where('entity_id', $entityId)->where('field', $field)->where('status', 'DIAJUKAN')->exists()) {
            throw ValidationException::withMessages(['field' => 'Sudah ada koreksi yang menunggu persetujuan untuk bidang ini.']);
        }

        $c = DataCorrection::create([
            'application_id' => $app->id, 'entity' => $entity, 'entity_id' => $entityId, 'field' => $field,
            'old_value' => $old, 'new_value' => $new, 'reason' => $reason, 'requested_by' => $user->id, 'status' => 'DIAJUKAN',
        ]);
        $this->audit->record('CORRECTION_REQUESTED', $c, ['value' => $old], ['value' => $new, 'field' => "$entity.$field", 'reason' => $reason], 'PROSES', $app->id, $user);
        $this->notifier->send($app, self::fields()[$entity][$field], 'Koreksi data menunggu persetujuan',
            "Koreksi $entity.$field diajukan: \"$old\" → \"$new\". Alasan: $reason", 'warning', $user->id);

        return $c;
    }

    public function canDecide(DataCorrection $c, User $user): bool
    {
        $roles = self::fields()[$c->entity][$c->field] ?? [];

        return $c->status === 'DIAJUKAN'
            && $c->requested_by !== $user->id                       // 4-mata
            && $user->hasRole(...$roles)
            && Permission::allows($user, 'koreksi_data', 'P')
            && GrantApplication::query()->visibleTo($user)->whereKey($c->application_id)->exists();
    }

    public function decide(DataCorrection $c, User $user, bool $approve, ?string $note): DataCorrection
    {
        $c = $c->fresh() ?? $c;
        if ($c->requested_by === $user->id) {
            throw new AuthorizationException('Pengaju koreksi tidak boleh menyetujui koreksinya sendiri (prinsip 4-mata).');
        }
        if (! $this->canDecide($c, $user)) {
            throw new AuthorizationException('Anda bukan penyetuju yang berwenang untuk koreksi ini.');
        }
        if (! $approve && blank($note)) {
            throw ValidationException::withMessages(['decision_note' => 'Alasan penolakan wajib diisi.']);
        }

        return DB::transaction(function () use ($c, $user, $approve, $note) {
            $c = DataCorrection::query()->lockForUpdate()->findOrFail($c->id);
            if ($c->status !== 'DIAJUKAN') {
                throw ValidationException::withMessages(['workflow' => 'Koreksi ini sudah diputuskan.']);
            }
            $app = GrantApplication::findOrFail($c->application_id);

            if ($approve) {
                $model = $this->resolve($app, $c->entity, $c->entity_id);
                $this->validateValue($model, $c->field, (string) $c->new_value);
                $current = $this->stringify($model->getAttribute($c->field));
                if ($current !== $c->old_value) {
                    throw ValidationException::withMessages(['workflow' => 'Data telah berubah sejak koreksi diajukan. Ajukan ulang koreksi.']);
                }
                try {
                    $model->setAttribute($c->field, $c->new_value);
                    $model->save();
                } catch (QueryException $e) {
                    throw ValidationException::withMessages(['workflow' => 'Nilai baru ditolak database (mungkin duplikat).']);
                }
                $app->increment('lock_version');
            }

            $c->update([
                'status' => $approve ? 'DISETUJUI' : 'DITOLAK', 'decided_by' => $user->id, 'decision_note' => $note,
                'decided_at' => now(), 'applied_at' => $approve ? now() : null,
            ]);
            $this->audit->record($approve ? 'CORRECTION_APPLIED' : 'CORRECTION_REJECTED', $c, ['value' => $c->old_value],
                ['value' => $c->new_value, 'field' => "{$c->entity}.{$c->field}", 'note' => $note], 'PROSES', $app->id, $user);

            return $c;
        });
    }

    private function resolve(GrantApplication $app, string $entity, int $id): Model
    {
        $class = self::modelFor($entity);
        $model = $class::query()->find($id);
        $belongs = match ($entity) {
            'application' => $model && $model->id === $app->id,
            'decree' => $model && $app->decision && $app->decision->decree_id === $model->id,
            default => $model && $model->application_id === $app->id,
        };
        if (! $belongs) {
            throw ValidationException::withMessages(['entity_id' => 'Data yang dikoreksi tidak terkait dengan usulan ini.']);
        }

        return $model;
    }

    private function validateValue(Model $model, string $field, string $value): void
    {
        if ($value === '') {
            throw ValidationException::withMessages(['new_value' => 'Nilai baru tidak boleh kosong.']);
        }
        if (in_array($field, self::DATE_FIELDS, true)) {
            try {
                $d = Carbon::createFromFormat('Y-m-d', $value);
                if ($d === false || $d->format('Y-m-d') !== $value) {
                    throw new \Exception;
                }
            } catch (\Throwable) {
                throw ValidationException::withMessages(['new_value' => 'Format tanggal harus YYYY-MM-DD.']);
            }
            if ($model instanceof GrantApplication) {
                $start = $field === 'activity_start' ? $value : $model->activity_start?->toDateString();
                $end = $field === 'activity_end' ? $value : $model->activity_end?->toDateString();
                if ($start && $end && $end < $start) {
                    throw ValidationException::withMessages(['new_value' => 'Tanggal selesai kegiatan tidak boleh sebelum tanggal mulai.']);
                }
            }
        } elseif (mb_strlen($value) > 2000) {
            throw ValidationException::withMessages(['new_value' => 'Teks terlalu panjang.']);
        }
    }

    private function stringify(mixed $v): string
    {
        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (string) $v;
    }
}
