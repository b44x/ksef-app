<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Polish companies have a NIP; foreign ones and consumers may not.
            $table->string('nip', 10)->nullable();
            $table->string('country', 2)->default('PL');
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'nip']);
            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractors');
    }
};
