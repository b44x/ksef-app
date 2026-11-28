<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // What a new invoice starts with, so it is not typed again every time.
            $table->unsignedSmallInteger('default_payment_days')->nullable()->default(14);
            $table->unsignedTinyInteger('default_payment_method')->nullable();
            $table->string('default_bank_account')->nullable();
            $table->text('default_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['default_payment_days', 'default_payment_method', 'default_bank_account', 'default_notes']);
        });
    }
};
