<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pengaturan cetak kartu anggota (bebas ditambah user): ukuran kartu
     * bebas (mm), field mana yang tampil beserta posisinya diatur di
     * member_card_fields. Bukan WYSIWYG — pengaturan lewat form posisi X/Y.
     */
    public function up(): void
    {
        Schema::create('member_card_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('width_mm', 6, 2)->default(85.60);
            $table->decimal('height_mm', 6, 2)->default(53.98);
            $table->string('background_color', 7)->default('#FFFFFF');
            $table->string('background_image_path')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_card_templates');
    }
};
