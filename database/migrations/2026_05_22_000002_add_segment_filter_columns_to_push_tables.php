<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'titi';

    public function up(): void
    {
        Schema::connection('titi')->table('titi_campaign_pushes', function (Blueprint $table) {
            $table->string('target_segment')->nullable()->after('target_tester_id');
            $table->json('target_filters')->nullable()->after('target_segment');
        });

        Schema::connection('titi')->table('titi_standalone_pushes', function (Blueprint $table) {
            $table->string('target_segment')->nullable()->after('target_tester_id');
            $table->json('target_filters')->nullable()->after('target_segment');
        });
    }

    public function down(): void
    {
        Schema::connection('titi')->table('titi_campaign_pushes', function (Blueprint $table) {
            $table->dropColumn(['target_segment', 'target_filters']);
        });

        Schema::connection('titi')->table('titi_standalone_pushes', function (Blueprint $table) {
            $table->dropColumn(['target_segment', 'target_filters']);
        });
    }
};
