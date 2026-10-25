<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->string('description')->nullable();
            $t->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->primary(['user_id', 'role_id']);
        });

        Schema::create('fiscal_years', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('year')->unique();
            $t->string('status', 10)->default('RENCANA'); // RENCANA|BERJALAN|TUTUP
            $t->unsignedInteger('last_seq')->default(0);  // penomoran register usulan
            $t->date('submission_open')->nullable();
            $t->date('submission_close')->nullable();
            $t->timestamps();
        });

        // Jenis hibah. `form` menentukan jalur: UANG -> SP2D, BARANG -> BAST (Blueprint Bab 4)
        // `scope` memisahkan hibah dari bansos (Matriks Kepatuhan Pasal 22-44: di luar workflow hibah)
        Schema::create('grant_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->string('form', 10);               // UANG | BARANG
            $t->string('scope', 10)->default('HIBAH'); // HIBAH | BANSOS
            $t->boolean('is_active')->default(true);
            $t->text('requirements_note')->nullable();
            $t->timestamps();
        });

        // Daftar regulasi + status berlaku (Pasal 45-46: aturan lama tidak boleh jadi dasar aktif)
        Schema::create('regulations', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('title');
            $t->string('type', 20);                // UU|PP|PERMENDAGRI|PERDA|PERBUP|KEPUTUSAN
            $t->string('status', 14)->default('AKTIF'); // AKTIF|DICABUT|PERLU_DICEK
            $t->boolean('is_grant_basis')->default(false); // boleh dipilih sebagai dasar hukum usulan
            $t->date('enacted_at')->nullable();
            $t->date('effective_at')->nullable();
            $t->foreignId('revoked_by_id')->nullable()->constrained('regulations')->nullOnDelete();
            $t->date('revoked_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        // Master dokumen wajib per tahap / bentuk hibah / kategori penerima
        Schema::create('document_requirements', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40);
            $t->string('name');
            $t->string('stage', 12);                  // PENGAJUAN|PENCAIRAN|PENYERAHAN|LPJ
            $t->string('grant_form', 10)->nullable(); // null = uang & barang
            $t->foreignId('recipient_category_id')->nullable()->constrained('recipient_categories')->nullOnDelete();
            $t->boolean('is_mandatory')->default(true);
            $t->boolean('requires_validity')->default(false); // punya masa berlaku
            $t->string('template_ref', 30)->nullable();       // mis. "Lampiran C.1"
            $t->string('help')->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['stage', 'grant_form']);
        });

        // Pagu hibah per OPD per tahun anggaran
        Schema::create('budget_ceilings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fiscal_year_id')->constrained();
            $t->foreignId('opd_id')->constrained('organizations');
            $t->string('account_code', 40)->nullable();
            $t->string('dpa_ref', 80)->nullable();
            $t->unsignedBigInteger('ceiling');
            $t->text('notes')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['fiscal_year_id', 'opd_id']);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 80)->primary();
            $t->text('value');            // JSON
            $t->string('description')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'budget_ceilings', 'document_requirements', 'regulations', 'grant_types', 'fiscal_years', 'user_roles', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
