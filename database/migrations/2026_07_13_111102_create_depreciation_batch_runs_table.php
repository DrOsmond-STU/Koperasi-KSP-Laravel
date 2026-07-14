<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * AUD-06: eksekusi batch penyusutan per periode wajib tercatat — run
     * id, periode, jumlah aset diproses. `fixed_asset_depreciation_entries`
     * itself has no single row representing "the batch as a whole", so
     * DepreciationEngine::run() creates exactly one row here per
     * invocation; the Auditable trait (applied to the model, not this
     * migration) records the `create` audit log automatically.
     */
    public function up(): void
    {
        Schema::create('depreciation_batch_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7);
            $table->unsignedInteger('assets_processed');
            $table->unsignedInteger('assets_skipped');
            $table->foreignId('triggered_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('depreciation_batch_runs');
    }
};
