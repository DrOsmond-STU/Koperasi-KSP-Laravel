<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PII columns (nik, address, phone, email, date_of_birth) are `text` and
     * encrypted at the model layer (Eloquent `encrypted` cast — SECURITY.md
     * §PII handling) — `text` because ciphertext is far longer than
     * plaintext and would truncate a `string`/varchar column.
     */
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('member_type_id')->constrained('member_types')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('member_number')->unique();
            $table->string('name');
            $table->text('nik')->nullable();
            $table->text('address')->nullable();
            $table->text('phone')->nullable();
            $table->text('email')->nullable();
            $table->text('date_of_birth')->nullable();
            $table->enum('status', ['calon', 'aktif', 'nonaktif', 'keluar'])->default('calon');
            $table->date('joined_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
