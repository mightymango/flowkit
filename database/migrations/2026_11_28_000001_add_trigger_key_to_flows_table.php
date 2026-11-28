<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MightyMango\Flowkit\Engine\TriggerKey;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flows', function (Blueprint $table) {
            // What the live version's trigger listens for, e.g.
            // `core.model-event:App\Models\Learner:changed`, so a model save
            // only loads the flows that could match it. Null unless live.
            $table->string('trigger_key')->nullable()->after('live_version_id')->index();
        });

        // Backfill flows that are already live, so existing tenants keep firing.
        DB::table('flows')
            ->join('flow_versions', 'flow_versions.id', '=', 'flows.live_version_id')
            ->where('flows.status', 'live')
            ->select('flows.id', 'flow_versions.definition')
            ->orderBy('flows.id')
            ->each(function (object $row) {
                $definition = is_string($row->definition) ? json_decode($row->definition, true) : null;

                DB::table('flows')->where('id', $row->id)->update([
                    'trigger_key' => TriggerKey::fromDefinition(is_array($definition) ? $definition : null),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('flows', function (Blueprint $table) {
            $table->dropIndex(['trigger_key']);
            $table->dropColumn('trigger_key');
        });
    }
};
