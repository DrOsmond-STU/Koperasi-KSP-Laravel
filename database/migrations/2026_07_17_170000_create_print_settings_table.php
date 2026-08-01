<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Singleton (always id=1) — mirrors app_branding_settings. Controls the
     * shared letterhead/paper layout every new cetakan (prints/layout.blade.php)
     * renders through, so admin can tune it once instead of per-document.
     */
    public function up(): void
    {
        Schema::create('print_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('paper_size', ['a4', 'letter', 'f4'])->default('a4');
            $table->enum('orientation', ['portrait', 'landscape'])->default('portrait');
            $table->unsignedSmallInteger('margin_mm')->default(15);
            $table->unsignedTinyInteger('font_size_pt')->default(11);
            $table->boolean('show_address')->default(true);
            $table->string('address_text')->nullable();
            $table->string('footer_text')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_settings');
    }
};
