<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Where the daily summary goes (nothing is sent without an address) and what it may contain.
            $table->string('notify_email')->nullable();
            $table->boolean('notify_offline')->default(true);
            $table->boolean('notify_purchases')->default(true);
            $table->boolean('notify_rejected')->default(true);
            // What the last summary already told: only newer purchases and rejections are news.
            $table->timestamp('last_digest_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['notify_email', 'notify_offline', 'notify_purchases', 'notify_rejected', 'last_digest_at']);
        });
    }
};
