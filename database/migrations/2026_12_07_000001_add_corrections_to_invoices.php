<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // A correction points at the invoice it corrects (the original, even when it corrects a correction's result).
            $table->foreignId('corrects_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('correction_reason', 500)->nullable();
            // 1: the effect is back-dated to the original invoice's period, 2: to the correction's, 3: some other period.
            $table->unsignedTinyInteger('correction_type')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrects_invoice_id');
            $table->dropColumn(['correction_reason', 'correction_type']);
        });
    }
};
