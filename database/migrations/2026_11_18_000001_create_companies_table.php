<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('nip', 10)->unique();
            $table->string('environment', 16);
            $table->string('auth_method', 16);
            // Token, or certificate and private key: encrypted with the application key.
            $table->text('credentials')->nullable();
            $table->timestamps();
        });

        // The SDK's access and refresh tokens, so that not every request logs in again. Encrypted.
        Schema::create('ksef_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('token_key', 64);
            $table->text('payload');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['company_id', 'token_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ksef_tokens');
        Schema::dropIfExists('companies');
    }
};
