<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // What was issued, frozen: the FA(3) document KSeF receives, and the parties as they were that day
            // (a contractor edited later must not change an invoice that already exists).
            $table->longText('xml')->nullable()->after('notes');
            $table->json('snapshot')->nullable()->after('xml');
            $table->timestamp('issued_at')->nullable()->after('snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['xml', 'snapshot', 'issued_at']);
        });
    }
};
