<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'titi';

    public function up(): void
    {
        Schema::connection('titi')->create('titi_standalone_pushes', function (Blueprint $table) {
            $table->id();
            $table->string('onesignal_id')->nullable();

            // Content
            $table->string('title');
            $table->text('message');
            $table->string('subtitle')->nullable();
            $table->string('image', 500)->nullable();
            $table->string('push_url', 500)->nullable();

            // Targeting
            $table->enum('target_type', ['all', 'testers', 'tester', 'store'])->default('all');
            $table->unsignedInteger('target_store_id')->nullable();
            $table->unsignedInteger('target_tester_id')->nullable();

            // Delivery
            $table->unsignedInteger('ttl')->nullable();          // seconds; null = OneSignal default (3 days)
            $table->tinyInteger('priority')->default(10);        // 10=high, 5=normal
            $table->string('collapse_id')->nullable();           // replaces notification with same ID on device

            // iOS badge
            $table->string('ios_badge_type', 20)->nullable();   // None | SetTo | Increase
            $table->integer('ios_badge_count')->nullable();

            // Scheduling & status
            $table->timestamp('send_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'cancelled'])->default('pending');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('titi')->dropIfExists('titi_standalone_pushes');
    }
};
