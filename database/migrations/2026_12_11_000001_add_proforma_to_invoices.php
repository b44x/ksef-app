<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // 'vat': a VAT invoice (goes to KSeF). 'proforma': an offer-like document that is never sent to KSeF and is not a sale.
            $table->string('kind', 12)->default('vat');
            // The VAT invoice that was made from a proforma, and the proforma it came from.
            $table->foreignId('converted_from_id')->nullable()->constrained('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_from_id');
            $table->dropColumn('kind');
        });
    }
};
