<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit trail: append-only + rantai hash (tamper-evident).
        // old/new disimpan sebagai TEXT agar hash dapat dihitung ulang persis (jsonb mengurutkan ulang kunci).
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->string('category', 10)->default('PROSES'); // PROSES | AKSES | SISTEM
            // sengaja tanpa FK: baris log tidak boleh tersentuh (SET NULL = UPDATE) bila pengguna dihapus
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('user_name')->nullable();
            $t->string('role_code', 20)->nullable();
            $t->string('action', 40);
            $t->string('entity_type', 40)->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->unsignedBigInteger('application_id')->nullable();
            $t->text('old_values')->nullable();
            $t->text('new_values')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->char('prev_hash', 64)->nullable();
            $t->char('hash', 64);
            $t->timestamp('created_at', 0);
            $t->index(['entity_type', 'entity_id']);
            $t->index('application_id');
            $t->index('created_at');
            $t->index(['category', 'action']);
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION audit_logs_immutable() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'audit_logs bersifat append-only: % tidak diizinkan', TG_OP;
END;
$$ LANGUAGE plpgsql;
SQL);
            DB::unprepared('CREATE TRIGGER audit_logs_no_update BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION audit_logs_immutable()');
            DB::unprepared('CREATE TRIGGER audit_logs_no_truncate BEFORE TRUNCATE ON audit_logs FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_immutable()');
        } elseif ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs bersifat append-only'); END");
            DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs bersifat append-only'); END");
        }

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_truncate ON audit_logs');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs');
            DB::unprepared('DROP FUNCTION IF EXISTS audit_logs_immutable()');
        }
        Schema::dropIfExists('audit_logs');
    }
};
