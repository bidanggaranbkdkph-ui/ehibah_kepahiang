<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipient_categories', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('legal_basis', 40)->nullable(); // mis. "Perbup 25/2023 Pasal 3"
            $t->boolean('is_parpol')->default(false);
            $t->boolean('requires_legal_entity')->default(false);
            $t->text('special_rules')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // Perangkat daerah (OPD) maupun lembaga/ormas penerima
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('type', 10); // OPD | LEMBAGA
            $t->foreignId('recipient_category_id')->nullable()->constrained('recipient_categories')->nullOnDelete();
            $t->text('address')->nullable();
            $t->string('contact')->nullable();
            $t->string('npwp', 30)->nullable();
            $t->string('legal_doc_no')->nullable();   // akta / SK pengesahan
            $t->date('legal_doc_date')->nullable();
            $t->string('chair_name')->nullable();      // ketua
            $t->string('secretary_name')->nullable();  // sekretaris (wajib parpol)
            $t->string('bank_name')->nullable();
            $t->string('bank_account_no', 40)->nullable();
            $t->string('bank_account_name')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index('type');
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $t->string('name');
            $t->string('nip_nik', 30)->nullable()->unique();
            $t->string('email')->unique();
            $t->string('phone', 30)->nullable();
            $t->string('position')->nullable();
            $t->string('password');
            // SOP 1: registrasi dan validasi akun oleh admin layanan
            $t->string('account_status', 12)->default('PENDING'); // PENDING|ACTIVE|SUSPENDED|REJECTED
            $t->boolean('is_active')->default(false);
            $t->timestamp('email_verified_at')->nullable();
            $t->text('mfa_secret')->nullable();
            $t->timestamp('mfa_enabled_at')->nullable();
            $t->unsignedSmallInteger('failed_login_attempts')->default(0);
            $t->timestamp('locked_until')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
            $t->timestamp('password_changed_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->index('account_status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('recipient_categories');
    }
};
