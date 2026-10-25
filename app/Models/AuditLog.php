<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Baris audit bersifat append-only. Pengamanan berlapis:
 *  1. model menolak update/delete,
 *  2. trigger database (PostgreSQL/SQLite) menolak UPDATE/DELETE/TRUNCATE,
 *  3. rantai hash (prev_hash -> hash) agar manipulasi langsung ke tabel terdeteksi `audit:verify`.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Log audit tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Log audit tidak dapat dihapus.'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
