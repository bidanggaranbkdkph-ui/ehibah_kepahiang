<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditService
{
    /**
     * Catat satu peristiwa. Aman dipanggil di dalam transaksi pemanggil.
     *
     * @param  Model|array|null  $entity  model terkait atau [type, id]
     */
    public function record(
        string $action,
        Model|array|null $entity = null,
        ?array $old = null,
        ?array $new = null,
        string $category = 'PROSES',
        ?int $applicationId = null,
        ?User $actor = null,
    ): AuditLog {
        $actor ??= Auth::user();
        [$type, $id] = $this->entityRef($entity);

        if ($applicationId === null && $entity instanceof Model) {
            $applicationId = $entity->getTable() === 'grant_applications'
                ? $entity->getKey()
                : ($entity->getAttribute('application_id'));
        }

        $request = app()->bound('request') ? request() : null;

        return DB::transaction(function () use ($action, $type, $id, $old, $new, $category, $applicationId, $actor, $request) {
            // Serialisasi penulisan agar rantai hash tidak bercabang pada beban paralel.
            if (DB::getDriverName() === 'pgsql') {
                DB::select('select pg_advisory_xact_lock(?)', [7_300_001]);
            }

            $prev = AuditLog::query()->orderByDesc('id')->value('hash');
            $createdAt = now()->format('Y-m-d H:i:s');
            $oldJson = $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $newJson = $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $row = [
                'category' => $category,
                'user_id' => $actor?->id,
                'user_name' => $actor?->name,
                'role_code' => $actor?->primaryRole(),
                'action' => $action,
                'entity_type' => $type,
                'entity_id' => $id,
                'application_id' => $applicationId,
                'old_values' => $oldJson,
                'new_values' => $newJson,
                'ip' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'prev_hash' => $prev,
                'created_at' => $createdAt,
            ];
            $row['hash'] = self::computeHash($row);

            return AuditLog::create($row);
        });
    }

    public function access(string $action, ?User $actor = null, ?array $meta = null): AuditLog
    {
        return $this->record($action, null, null, $meta, 'AKSES', null, $actor);
    }

    public function system(string $action, ?Model $entity = null, ?array $old = null, ?array $new = null): AuditLog
    {
        return $this->record($action, $entity, $old, $new, 'SISTEM');
    }

    public static function computeHash(array $row): string
    {
        return hash('sha256', implode('|', [
            $row['prev_hash'] ?? '', $row['category'], $row['user_id'] ?? '', $row['action'],
            $row['entity_type'] ?? '', $row['entity_id'] ?? '', $row['application_id'] ?? '',
            $row['old_values'] ?? '', $row['new_values'] ?? '', $row['created_at'],
        ]));
    }

    /**
     * Verifikasi seluruh rantai. Mengembalikan null bila utuh, atau ['id'=>..,'reason'=>..] pada baris pertama yang rusak.
     */
    public function verifyChain(): ?array
    {
        $prev = null;
        $broken = null;

        AuditLog::query()->orderBy('id')->chunk(500, function ($rows) use (&$prev, &$broken) {
            foreach ($rows as $r) {
                $data = $r->getAttributes();
                $data['created_at'] = $r->created_at->format('Y-m-d H:i:s');
                if (($data['prev_hash'] ?? null) !== $prev) {
                    $broken = ['id' => $r->id, 'reason' => 'prev_hash tidak cocok (baris hilang/disisipkan)'];

                    return false;
                }
                if (self::computeHash($data) !== $r->hash) {
                    $broken = ['id' => $r->id, 'reason' => 'isi baris berubah (hash tidak cocok)'];

                    return false;
                }
                $prev = $r->hash;
            }
        });

        return $broken;
    }

    private function entityRef(Model|array|null $entity): array
    {
        if ($entity instanceof Model) {
            return [class_basename($entity), $entity->getKey()];
        }
        if (is_array($entity)) {
            return [$entity[0] ?? null, $entity[1] ?? null];
        }

        return [null, null];
    }
}
