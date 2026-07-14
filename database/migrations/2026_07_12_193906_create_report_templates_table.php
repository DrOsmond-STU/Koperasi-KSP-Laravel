<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Report Builder Dinamis (PRD §17, Task 4.5). `report_type` is a key
     * into ReportTypeRegistry (server-side whitelist, not user-supplied
     * SQL) — `columns`/`filters` are only ever validated against that
     * whitelist, never trusted as-is (RPT-10). A template with
     * `shared_role_id` set is visible to that whole role; otherwise it's
     * private to `created_by` (RPT-11).
     */
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('report_type');
            $table->json('columns');
            $table->json('filters')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
