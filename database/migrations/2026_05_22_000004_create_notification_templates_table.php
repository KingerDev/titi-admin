<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'titi';

    public function up(): void
    {
        Schema::connection('titi')->create('titi_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title');
            $table->text('message');
            $table->string('subtitle')->nullable();
            $table->string('image', 500)->nullable();
            $table->string('push_url', 500)->nullable();
            $table->enum('target_type', ['all', 'testers', 'tester', 'store', 'segment', 'filtered'])->default('all');
            $table->unsignedInteger('target_store_id')->nullable();
            $table->unsignedInteger('target_tester_id')->nullable();
            $table->string('target_segment')->nullable();
            $table->json('target_filters')->nullable();
            $table->unsignedInteger('ttl')->nullable();
            $table->tinyInteger('priority')->default(10);
            $table->string('collapse_id')->nullable();
            $table->string('ios_badge_type', 20)->nullable();
            $table->integer('ios_badge_count')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('titi')->dropIfExists('titi_notification_templates');
    }
};
