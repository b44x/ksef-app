<?php

use App\Invoicing\Totals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // The sums as the SDK computed them when the invoice was issued: reports and payments are made from these.
            $table->decimal('net_total', 14, 2)->nullable();
            $table->decimal('vat_total', 14, 2)->nullable();
            $table->decimal('gross_total', 14, 2)->nullable();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'paid_on']);
        });

        // Invoices issued before this migration get their sums now.
        foreach (DB::table('invoices')->where('status', '!=', 'draft')->get(['id', 'currency']) as $invoice) {
            $lines = DB::table('invoice_lines')->where('invoice_id', $invoice->id)->get(['name', 'quantity', 'unit_net_price', 'vat_rate'])
                ->map(fn ($l): array => (array) $l)->all();
            if ($lines === []) {
                continue;
            }
            $totals = Totals::of($lines, $invoice->currency);
            DB::table('invoices')->where('id', $invoice->id)->update(['net_total' => $totals['net'], 'vat_total' => $totals['vat'], 'gross_total' => $totals['gross']]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['net_total', 'vat_total', 'gross_total']);
        });
    }
};
