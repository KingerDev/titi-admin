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
            $table->string('send_error')->nullable()->after('status');
            $table->unsignedTinyInteger('retry_count')->default(0)->after('send_error');
        });

        Schema::connection('titi')->table('titi_standalone_pushes', function (Blueprint $table) {
            $table->string('send_error')->nullable()->after('status');
            $table->unsignedTinyInteger('retry_count')->default(0)->after('send_error');
        });

        // Extend status enum to include 'error'
        \Illuminate\Support\Facades\DB::connection('titi')
            ->statement("ALTER TABLE titi_campaign_pushes MODIFY COLUMN status ENUM('pending','sent','cancelled','error') DEFAULT 'pending'");

        \Illuminate\Support\Facades\DB::connection('titi')
            ->statement("ALTER TABLE titi_standalone_pushes MODIFY COLUMN status ENUM('pending','sent','cancelled','error') DEFAULT 'pending'");
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::connection('titi')
            ->statement("ALTER TABLE titi_campaign_pushes MODIFY COLUMN status ENUM('pending','sent','cancelled') DEFAULT 'pending'");

        \Illuminate\Support\Facades\DB::connection('titi')
            ->statement("ALTER TABLE titi_standalone_pushes MODIFY COLUMN status ENUM('pending','sent','cancelled') DEFAULT 'pending'");

        Schema::connection('titi')->table('titi_campaign_pushes', function (Blueprint $table) {
            $table->dropColumn(['send_error', 'retry_count']);
        });

        Schema::connection('titi')->table('titi_standalone_pushes', function (Blueprint $table) {
            $table->dropColumn(['send_error', 'retry_count']);
        });
    }
};
