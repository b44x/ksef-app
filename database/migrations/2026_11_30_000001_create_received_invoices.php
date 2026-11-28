<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('received_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // The invoice as KSeF stores it: the XML is the document, the columns only make the list searchable.
            $table->string('ksef_number', 35);
            $table->longText('xml');
            $table->string('number');
            $table->string('type', 10);
            $table->date('issue_date');
            $table->string('seller_name');
            $table->string('seller_nip', 30)->nullable();
            $table->string('currency', 3);
            $table->decimal('gross', 18, 2);
            $table->timestamp('received_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'ksef_number']);
            $table->index(['company_id', 'issue_date']);
            $table->index(['company_id', 'seller_name']);
        });

        Schema::table('companies', function (Blueprint $table) {
            // Where the purchase synchronisation stopped: the next one continues from here.
            $table->timestamp('purchases_synced_until')->nullable();
            $table->timestamp('purchases_synced_at')->nullable();
            $table->text('purchases_sync_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['purchases_synced_until', 'purchases_synced_at', 'purchases_sync_error']);
        });
        Schema::dropIfExists('received_invoices');
    }
};
