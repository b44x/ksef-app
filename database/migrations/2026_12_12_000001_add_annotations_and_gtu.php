<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Remarks the Polish VAT rules ask for on certain invoices (FA(3) "Adnotacje").
            $table->boolean('split_payment')->default(false);      // mechanizm podzielonej płatności
            $table->boolean('cash_accounting')->default(false);    // metoda kasowa
            $table->string('exemption_basis', 256)->nullable();    // podstawa zwolnienia z VAT (lines with the "zw" rate)
        });
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('gtu', 6)->nullable();                  // GTU_01 ... GTU_13: goods and services groups
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('default_cash_accounting')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('default_cash_accounting'));
        Schema::table('invoice_lines', fn (Blueprint $t) => $t->dropColumn('gtu'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['split_payment', 'cash_accounting', 'exemption_basis']));
    }
};
