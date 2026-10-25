<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // Pusat relasi: satu permohonan -> banyak catatan proses
        // Nilai uang disimpan sebagai rupiah bulat (bigint), tanpa desimal.
        // ---------------------------------------------------------------
        Schema::create('grant_applications', function (Blueprint $t) {
            $t->id();
            $t->string('reg_number', 40)->nullable()->unique();   // dibuat saat diajukan
            $t->foreignId('fiscal_year_id')->constrained();
            $t->foreignId('applicant_org_id')->constrained('organizations');
            $t->foreignId('opd_id')->constrained('organizations'); // OPD pemilik urusan
            $t->foreignId('grant_type_id')->constrained();
            $t->foreignId('recipient_category_id')->constrained();
            $t->foreignId('regulation_id')->nullable()->constrained('regulations');
            $t->foreignId('created_by')->constrained('users');

            // Isi usulan (Perbup Pasal 6)
            $t->string('title');
            $t->text('background')->nullable();
            $t->text('purpose')->nullable();     // maksud & tujuan
            $t->text('target')->nullable();      // sasaran
            $t->text('program_activity')->nullable();
            $t->string('location')->nullable();
            $t->date('activity_start')->nullable();
            $t->date('activity_end')->nullable();
            $t->text('committee')->nullable();   // JSON susunan organisasi/panitia
            $t->boolean('is_recurring')->default(false);   // Pasal 2: tidak rutin kecuali pengecualian
            $t->text('recurring_reason')->nullable();
            $t->boolean('funding_declaration')->default(false); // Pasal 4: tidak tumpang tindih APBN/sumber lain

            $t->unsignedBigInteger('amount_requested')->default(0);
            $t->unsignedBigInteger('amount_recommended')->nullable();
            $t->unsignedBigInteger('amount_approved')->nullable();

            $t->string('status', 14)->default('DRAFT');
            $t->unsignedSmallInteger('revision_no')->default(0);
            $t->timestamp('status_changed_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->text('cancellation_reason')->nullable();

            // Penyaluran (Pasal 9): STAGED = 70/30, FULL = 100% (pengecualian berotorisasi)
            $t->string('disbursement_scheme', 8)->default('STAGED');
            $t->text('full_exception_reason')->nullable();
            $t->foreignId('full_exception_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('full_exception_at')->nullable();

            // Pertanggungjawaban (Pasal 16-21)
            $t->date('activity_completed_at')->nullable();
            $t->date('lpj_due_date')->nullable();
            $t->boolean('lpj_overdue')->default(false);
            $t->date('bpk_audit_date')->nullable();   // khusus parpol (Pasal 18)

            $t->unsignedInteger('lock_version')->default(1); // optimistic locking
            $t->timestamps();

            $t->index('status');
            $t->index(['fiscal_year_id', 'opd_id']);
            $t->index('applicant_org_id');
            $t->index('lpj_due_date');
        });

        // Rencana anggaran biaya / daftar barang yang diusulkan
        Schema::create('application_budget_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained('grant_applications')->cascadeOnDelete();
            $t->string('description');
            $t->decimal('quantity', 12, 2);
            $t->string('unit', 30);
            $t->unsignedBigInteger('unit_price');
            $t->unsignedBigInteger('subtotal');
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        // Dokumen unggahan: berversi, tidak pernah ditimpa
        Schema::create('application_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained('grant_applications')->cascadeOnDelete();
            $t->foreignId('requirement_id')->nullable()->constrained('document_requirements')->nullOnDelete();
            $t->string('doc_type', 40);
            $t->string('stage', 12);
            $t->string('related_type', 30)->nullable(); // disbursement|report|goods_delivery|nphd
            $t->unsignedBigInteger('related_id')->nullable();
            $t->unsignedSmallInteger('version')->default(1);
            $t->boolean('is_current')->default(true);
            $t->string('original_name');
            $t->string('file_path');
            $t->string('mime', 80);
            $t->unsignedBigInteger('size_bytes');
            $t->char('file_hash', 64);
            $t->date('valid_until')->nullable();
            $t->string('review_status', 14)->default('PENDING'); // PENDING|SESUAI|TIDAK_SESUAI
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('uploaded_by')->constrained('users');
            $t->timestamp('replaced_at')->nullable();
            $t->timestamps();
            $t->unique(['application_id', 'doc_type', 'related_type', 'related_id', 'version'], 'app_doc_version_unique');
            $t->index(['application_id', 'doc_type', 'is_current']);
        });

        Schema::create('verifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained('grant_applications')->cascadeOnDelete();
            $t->string('stage', 12);                 // ADMINISTRASI | TEKNIS
            $t->unsignedSmallInteger('round')->default(0); // = revision_no saat diperiksa
            $t->string('result', 14);                // LULUS | DIKEMBALIKAN | DITOLAK
            $t->text('checklist')->nullable();       // JSON: kriteria Pasal 2 / skor teknis
            $t->unsignedSmallInteger('score')->nullable(); // 0-100 (teknis)
            $t->text('notes')->nullable();
            $t->foreignId('verifier_id')->constrained('users');
            $t->timestamp('verified_at');
            $t->timestamps();
            $t->index(['application_id', 'stage']);
        });

        Schema::create('recommendations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->unique()->constrained('grant_applications')->cascadeOnDelete();
            $t->foreignId('opd_id')->constrained('organizations');
            $t->string('letter_no', 80);
            $t->date('letter_date');
            $t->unsignedBigInteger('amount_recommended');
            $t->text('notes')->nullable();
            $t->foreignId('issued_by')->constrained('users');
            $t->timestamp('issued_at');
            $t->timestamps();
        });

        Schema::create('tapd_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->unique()->constrained('grant_applications')->cascadeOnDelete();
            $t->unsignedBigInteger('amount_considered');
            $t->text('notes')->nullable();
            $t->foreignId('reviewer_id')->constrained('users');
            $t->timestamp('reviewed_at');
            $t->timestamps();
        });

        // Keputusan Bupati = daftar nominatif penerima (Pasal 7)
        Schema::create('decrees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fiscal_year_id')->constrained();
            $t->string('decree_no', 80)->unique();
            $t->date('decree_date');
            $t->string('title');
            $t->string('perda_ref', 120);      // Perda APBD
            $t->string('perkada_ref', 120);    // Perkada penjabaran APBD
            $t->string('file_path')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->unique()->constrained('grant_applications')->cascadeOnDelete();
            $t->foreignId('decree_id')->constrained('decrees');
            $t->string('result', 8);          // SETUJU | TOLAK
            $t->unsignedBigInteger('amount_approved');
            $t->text('notes')->nullable();
            $t->foreignId('decided_by')->constrained('users');
            $t->timestamp('decided_at');
            $t->timestamps();
        });

        // Alokasi anggaran per usulan terhadap pagu OPD
        Schema::create('budget_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->unique()->constrained('grant_applications')->cascadeOnDelete();
            $t->foreignId('ceiling_id')->constrained('budget_ceilings');
            $t->string('account_code', 40)->nullable();
            $t->string('dpa_ref', 80)->nullable();
            $t->unsignedBigInteger('amount');
            $t->string('status', 10)->default('RESERVED'); // RESERVED|CONFIRMED|RELEASED
            $t->foreignId('allocated_by')->constrained('users');
            $t->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('synced_at')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('released_at')->nullable();
            $t->string('release_reason')->nullable();
            $t->timestamps();
            $t->index(['ceiling_id', 'status']);
        });

        Schema::create('nphd_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained('grant_applications')->cascadeOnDelete();
            $t->string('nphd_no', 80);
            $t->date('nphd_date');
            $t->unsignedBigInteger('amount');
            $t->unsignedSmallInteger('version')->default(1);
            $t->string('status', 14)->default('DRAFT'); // DRAFT|DITANDATANGANI|BATAL
            $t->foreignId('signer_user_id')->nullable()->constrained('users')->nullOnDelete(); // Kepala SKPD
            $t->string('signer_name')->nullable();
            $t->string('signer_position')->nullable();
            $t->string('recipient_signer_name')->nullable();
            $t->string('signature_method', 8)->nullable(); // BASAH | TTE
            $t->string('signature_ref')->nullable();       // ref TTE (BSrE)
            $t->timestamp('signed_at')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->unique(['application_id', 'version']);
        });

        Schema::create('disbursements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained('grant_applications')->cascadeOnDelete();
            $t->unsignedSmallInteger('stage');           // 1, 2 (100% = tahap 1)
            $t->decimal('percentage', 5, 2);
            $t->unsignedBigInteger('amount');
            $t->string('status', 12)->default('DIAJUKAN'); // DIAJUKAN|DISALURKAN|DITOLAK|BATAL
            $t->string('idempotency_key', 80)->unique();   // cegah bayar ganda
            $t->string('spp_no', 60)->nullable();
            $t->date('spp_date')->nullable();
            $t->string('spm_no', 60)->nullable();
            $t->string('sp2d_no', 60)->nullable();
            $t->date('sp2d_date')->nullable();
            $t->string('rkud_ref', 80)->nullable();          // bukti transfer dari RKUD
            $t->string('bank_account_ref', 80)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->text('notes')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['application_id', 'stage']);
        });

        Schema::create('goods_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->unique()->constrained('grant_applications')->cascadeOnDelete();
            $t->string('procurement_ref', 120)->nullable(); // Pasal 13
            $t->date('procurement_date')->nullable();
            $t->string('bast_no', 80)->nullable();
            $t->date('bast_date')->nullable();
            $t->string('received_by_name')->nullable();
            $t->string('asset_ref', 120)->nullable();       // pencatatan keluar aset daerah
            $t->string('status', 12)->default('DRAFT');    // DRAFT|DISERAHKAN|DICATAT
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('asset_recorded_at')->nullable();
            $t->foreignId('asset_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('goods_delivery_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('goods_delivery_id')->constrained()->cascadeOnDelete();
            $t->string('description');
            $t->string('spec')->nullable();
            $t->decimal('quantity', 12, 2);
            $t->string('unit', 30);
            $t->unsignedBigInteger('unit_value')->default(0);
            $t->timestamps();
        });

        Schema::create('accountability_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained('grant_applications')->cascadeOnDelete();
            $t->string('report_type', 8);                  // TAHAP_1 | AKHIR
            $t->date('period_start')->nullable();
            $t->date('period_end')->nullable();
            $t->date('due_date')->nullable();
            $t->text('summary')->nullable();
            $t->text('activity_result')->nullable();
            $t->unsignedBigInteger('total_realization')->default(0);
            $t->unsignedBigInteger('remaining_funds')->default(0);
            $t->date('bpk_audit_date')->nullable();
            $t->string('review_status', 16)->default('DRAFT'); // DRAFT|DIAJUKAN|PERLU_PERBAIKAN|DITERIMA
            $t->text('review_notes')->nullable();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->boolean('is_late')->default(false);
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->index(['application_id', 'report_type']);
        });

        Schema::create('report_expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained('accountability_reports')->cascadeOnDelete();
            $t->date('spent_on');
            $t->string('description');
            $t->unsignedBigInteger('amount');
            $t->string('receipt_ref', 80)->nullable();
            $t->timestamps();
        });

        Schema::create('fund_returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained('grant_applications')->cascadeOnDelete();
            $t->foreignId('report_id')->nullable()->constrained('accountability_reports')->nullOnDelete();
            $t->unsignedBigInteger('amount');
            $t->date('returned_on');
            $t->string('kas_ref', 80);            // STS / referensi setoran kas daerah
            $t->foreignId('recorded_by')->constrained('users');
            $t->timestamp('notified_at')->nullable(); // pemberitahuan ke SKPD & tembusan BUD
            $t->timestamps();
        });

        Schema::create('warning_letters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained('grant_applications')->cascadeOnDelete();
            $t->unsignedSmallInteger('level');
            $t->string('letter_no', 80)->nullable();
            $t->date('letter_date');
            $t->date('lpj_due_date');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('monitoring_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained('grant_applications')->cascadeOnDelete();
            $t->string('type', 10)->default('MONEV');  // MONEV | EVALUASI
            $t->date('visited_at');
            $t->foreignId('officer_id')->constrained('users');
            $t->text('findings');
            $t->text('recommendation')->nullable();
            $t->text('follow_up')->nullable();
            $t->string('follow_up_status', 10)->default('OPEN'); // OPEN|SELESAI
            $t->date('follow_up_due')->nullable();
            $t->timestamp('sent_to_bupati_at')->nullable();      // Pasal 21
            $t->timestamp('sent_to_inspektorat_at')->nullable(); // tembusan
            $t->timestamps();
        });

        // Riwayat status (log_status pada blueprint ringkas)
        Schema::create('status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained('grant_applications')->cascadeOnDelete();
            $t->string('from_status', 14)->nullable();
            $t->string('to_status', 14);
            $t->string('action', 30);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('role_code', 20)->nullable();
            $t->text('note')->nullable();
            $t->timestamp('created_at');
            $t->index(['application_id', 'created_at']);
        });

        // Koreksi data pasca-kunci: usul -> setujui (4-mata) -> terapkan, semuanya tercatat
        Schema::create('data_corrections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained('grant_applications')->cascadeOnDelete();
            $t->string('entity', 20);
            $t->unsignedBigInteger('entity_id');
            $t->string('field', 40);
            $t->text('old_value')->nullable();
            $t->text('new_value')->nullable();
            $t->text('reason');
            $t->string('status', 10)->default('DIAJUKAN'); // DIAJUKAN|DISETUJUI|DITOLAK
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('decision_note')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('applied_at')->nullable();
            $t->timestamps();
            $t->index('status');
        });

        Schema::create('backup_logs', function (Blueprint $t) {
            $t->id();
            $t->string('type', 8);              // DB | FILES | FULL
            $t->string('status', 10);           // BERHASIL | GAGAL
            $t->string('path')->nullable();
            $t->unsignedBigInteger('size_bytes')->nullable();
            $t->char('sha256', 64)->nullable();
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->text('message')->nullable();
            $t->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            // Satu dokumen "current" per jenis (versi lama tetap tersimpan sebagai riwayat)
            DB::statement("CREATE UNIQUE INDEX app_doc_current_unique ON application_documents (application_id, doc_type, COALESCE(related_type, ''), COALESCE(related_id, 0)) WHERE is_current");
            // Satu penyaluran aktif per tahap (penolakan/pembatalan tidak dihitung)
            DB::statement("CREATE UNIQUE INDEX disb_active_stage_unique ON disbursements (application_id, stage) WHERE status IN ('DIAJUKAN','DISALURKAN')");
            DB::statement('ALTER TABLE grant_applications ADD CONSTRAINT chk_amounts CHECK (amount_requested >= 0)');
        }
    }

    public function down(): void
    {
        foreach ([
            'backup_logs', 'data_corrections', 'status_histories', 'monitoring_records', 'warning_letters',
            'fund_returns', 'report_expenses', 'accountability_reports', 'goods_delivery_items', 'goods_deliveries',
            'disbursements', 'nphd_documents', 'budget_allocations', 'decisions', 'decrees', 'tapd_reviews',
            'recommendations', 'verifications', 'application_documents', 'application_budget_items', 'grant_applications',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
