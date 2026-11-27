<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flows', function (Blueprint $table) {
            // The last minute (UTC) a schedule trigger claimed. `flowkit:tick`
            // claims it with an atomic update, so each scheduled minute
            // starts one run even when ticks overlap or run on two servers.
            $table->timestamp('last_scheduled_at')->nullable()->after('paused');
        });
    }

    public function down(): void
    {
        Schema::table('flows', function (Blueprint $table) {
            $table->dropColumn('last_scheduled_at');
        });
    }
};
