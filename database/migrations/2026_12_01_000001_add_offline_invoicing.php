<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // The KSeF "Offline" certificate with its private key (encrypted with the application key): the only copy of
            // the key. It confirms the issuer in QR code II; it cannot log in.
            $table->text('offline_certificate')->nullable();
            $table->timestamp('offline_certificate_expires_at')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Invoices issued without KSeF are delivered later, by a deadline that depends on why they were issued offline.
            $table->string('offline_mode', 16)->nullable();
            // When the failure or unavailability ended (the deadline counts from it); null while it is not known.
            $table->date('offline_anchor_date')->nullable();
            $table->timestamp('offline_deadline')->nullable();
            // The links in the two QR codes, as they were when the invoice was issued.
            $table->text('verification_url')->nullable();
            $table->text('issuer_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['offline_mode', 'offline_anchor_date', 'offline_deadline', 'verification_url', 'issuer_url']);
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['offline_certificate', 'offline_certificate_expires_at']);
        });
    }
};
