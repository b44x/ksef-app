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
            // Net and VAT per rate as the SDK summed them at issue: the VAT report is made from these.
            $table->json('vat_summary')->nullable();
        });

        foreach (DB::table('invoices')->where('status', '!=', 'draft')->get(['id', 'currency']) as $invoice) {
            $lines = DB::table('invoice_lines')->where('invoice_id', $invoice->id)->get(['name', 'quantity', 'unit_net_price', 'vat_rate'])
                ->map(fn ($l): array => (array) $l)->all();
            if ($lines !== []) {
                DB::table('invoices')->where('id', $invoice->id)->update(['vat_summary' => json_encode(Totals::of($lines, $invoice->currency)['buckets'])]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('vat_summary');
        });
    }
};
