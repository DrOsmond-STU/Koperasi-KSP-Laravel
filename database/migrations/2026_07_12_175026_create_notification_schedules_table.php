<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PRD §16 — jadwal pengingat (H-berapa, jam kirim) dikonfigurasi Admin
     * per jenis trigger. `day_offset` is negative for "before due" (H-3 =
     * -3, H-1 = -1, hari-H = 0) and positive for "overdue" reminders.
     */
    public function up(): void
    {
        Schema::create('notification_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('trigger_type');
            $table->integer('day_offset');
            $table->time('send_time')->default('08:00:00');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['trigger_type', 'day_offset']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_schedules');
    }
};
