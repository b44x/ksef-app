<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Restricted: a contractor or series that invoices point to cannot be deleted from under them.
            $table->foreignId('contractor_id')->constrained()->restrictOnDelete();
            $table->foreignId('numbering_series_id')->constrained('numbering_series')->restrictOnDelete();
            $table->string('status', 16)->default('draft');
            // Taken from the series when the invoice is issued; a draft has none.
            $table->string('number')->nullable();
            $table->date('issue_date');
            $table->date('sale_date')->nullable();
            $table->string('currency', 3)->default('PLN');
            $table->date('due_date')->nullable();
            $table->unsignedTinyInteger('payment_method')->nullable();
            $table->string('bank_account', 34)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'issue_date']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name', 512);
            $table->string('unit', 20)->nullable();
            $table->decimal('quantity', 18, 6);
            $table->decimal('unit_net_price', 20, 8);
            $table->string('vat_rate', 10);

            $table->unique(['invoice_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
