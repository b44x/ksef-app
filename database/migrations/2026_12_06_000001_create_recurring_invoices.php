<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('numbering_series_id')->constrained('numbering_series')->cascadeOnDelete();
            $table->foreignId('invoice_template_id')->nullable()->constrained('invoice_templates')->nullOnDelete();
            $table->string('name');
            $table->string('frequency', 16);
            // The day of the month the first one was due on: a monthly series started on the 31st stays on the last day.
            $table->unsignedTinyInteger('anchor_day');
            $table->date('next_run_on');
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true);
            // false: a draft to look over; true: issued at once (never sent: sending stays your decision).
            $table->boolean('auto_issue')->default(false);
            $table->unsignedSmallInteger('payment_days')->nullable();
            $table->unsignedTinyInteger('payment_method')->nullable();
            $table->string('bank_account')->nullable();
            $table->text('notes')->nullable();
            $table->json('lines');
            $table->unsignedInteger('generated_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('recurring_invoice_id')->nullable()->constrained('recurring_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_invoice_id');
        });
        Schema::dropIfExists('recurring_invoices');
    }
};
