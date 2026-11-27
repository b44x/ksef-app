<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit', 20)->default('szt.');
            $table->decimal('net_price', 14, 2);
            // An FA(3) rate code (23, 8, 5, 0 KR, zw, np I, ...), as the SDK's VatRate defines them.
            $table->string('vat_rate', 10)->default('23');
            $table->timestamps();

            $table->index(['company_id', 'name']);
        });

        Schema::create('numbering_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('format')->default('FV/{NNN}/{YYYY}');
            $table->string('reset')->default('yearly');
            $table->unsignedInteger('last_number')->default(0);
            // The year or month the last number belongs to; a new period restarts the count.
            $table->string('last_period')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_series');
        Schema::dropIfExists('products');
    }
};
