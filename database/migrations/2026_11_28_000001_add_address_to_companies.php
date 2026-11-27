<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The seller's data on every invoice. Nullable so an installation made before invoicing keeps working;
        // an invoice cannot be issued until the address is filled in.
        Schema::table('companies', function (Blueprint $table) {
            $table->string('address_line1')->nullable()->after('nip');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('email')->nullable()->after('address_line2');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['address_line1', 'address_line2', 'email']);
        });
    }
};
