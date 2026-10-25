<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->string('stage_code', 40); // SUBMISSION, VERIFICATION, TAPD_REVIEW, dll.
            $table->string('stage_name', 120);
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->string('regulation_ref')->nullable(); // Acuan pasal/regulasi
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            // Mode: AUTO (ikut tanggal), FORCE_OPEN (buka paksa), FORCE_CLOSED (tutup paksa)
            $table->string('control_mode', 20)->default('AUTO');
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fiscal_year_id', 'stage_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_schedules');
    }
};