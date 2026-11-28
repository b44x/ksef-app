<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->json('settings');
            $table->timestamps();
        });

        Schema::table('companies', function (Blueprint $table) {
            // The logo as a data URI (PNG or JPEG, small): it travels with the database, so a backup has it.
            $table->mediumText('logo')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Which template prints it; null means the default one. A deleted template falls back to the default.
            $table->foreignId('invoice_template_id')->nullable()->constrained('invoice_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_template_id');
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
        Schema::dropIfExists('invoice_templates');
    }
};
