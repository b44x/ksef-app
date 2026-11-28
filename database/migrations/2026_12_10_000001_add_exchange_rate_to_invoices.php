<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Foreign-currency invoices: the rate to PLN the VAT is converted with (NBP average of the last business day
            // before the sale, art. 31a), and the day that rate is from.
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->date('exchange_rate_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate', 'exchange_rate_date']);
        });
    }
};
