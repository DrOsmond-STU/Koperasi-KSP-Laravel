<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * LoanRepaymentService needs an unambiguous principal/interest split per
     * schedule row across possibly-multiple partial payments — `paid_amount`
     * alone can't be reverse-split without proportional-math drift. These
     * two columns are always kept in sync so paid_amount ==
     * paid_principal_amount + paid_interest_amount.
     */
    public function up(): void
    {
        Schema::table('loan_schedules', function (Blueprint $table) {
            $table->decimal('paid_principal_amount', 18, 2)->default(0)->after('paid_amount');
            $table->decimal('paid_interest_amount', 18, 2)->default(0)->after('paid_principal_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_schedules', function (Blueprint $table) {
            $table->dropColumn(['paid_principal_amount', 'paid_interest_amount']);
        });
    }
};
