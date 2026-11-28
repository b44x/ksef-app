<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // The references KSeF gave when it accepted the document for processing: enough to ask for the verdict
            // from any process, at any time.
            $table->string('ksef_session_reference')->nullable()->after('issued_at');
            $table->string('ksef_invoice_reference')->nullable()->after('ksef_session_reference');
            $table->string('ksef_invoice_hash')->nullable()->after('ksef_invoice_reference');
            // The verdict.
            $table->string('ksef_number')->nullable()->after('ksef_invoice_hash');
            $table->unsignedSmallInteger('ksef_status_code')->nullable()->after('ksef_number');
            $table->string('ksef_status_description')->nullable()->after('ksef_status_code');
            // What went wrong, in words, for the user: a refusal, a rejection with its reasons, or "still processing".
            $table->text('ksef_error')->nullable()->after('ksef_status_description');
            $table->longText('upo_xml')->nullable()->after('ksef_error');
            $table->unsignedSmallInteger('poll_attempts')->default(0)->after('upo_xml');
            $table->timestamp('sent_at')->nullable()->after('poll_attempts');
            $table->timestamp('accepted_at')->nullable()->after('sent_at');

            $table->unique(['company_id', 'ksef_number']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'ksef_number']);
            $table->dropColumn([
                'ksef_session_reference', 'ksef_invoice_reference', 'ksef_invoice_hash', 'ksef_number', 'ksef_status_code',
                'ksef_status_description', 'ksef_error', 'upo_xml', 'poll_attempts', 'sent_at', 'accepted_at',
            ]);
        });
    }
};
