<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per element placed on the card (photo, name, member number,
     * QR/barcode placeholder, free text, etc). Position/size in millimeters
     * so the same layout maps 1:1 from on-screen preview to printed PDF.
     */
    public function up(): void
    {
        Schema::create('member_card_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_card_template_id')->constrained('member_card_templates')->cascadeOnDelete();
            $table->string('field_key');
            $table->string('custom_text_value')->nullable();
            $table->decimal('position_x_mm', 6, 2);
            $table->decimal('position_y_mm', 6, 2);
            $table->decimal('width_mm', 6, 2)->nullable();
            $table->decimal('height_mm', 6, 2)->nullable();
            $table->unsignedSmallInteger('font_size_pt')->nullable();
            $table->string('font_weight', 10)->nullable();
            $table->string('text_align', 6)->nullable();
            $table->string('color', 7)->default('#16201C');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('member_card_template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_card_fields');
    }
};
