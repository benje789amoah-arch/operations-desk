<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A daily activity instance is the unit of work for one operational date.
     *
     * The status columns hold the *current* state and are only ever written by
     * RecordActivityUpdate, in the same transaction that appends the matching
     * activity_updates row, so the two cannot drift. "version" supports
     * optimistic locking: a save based on a stale version is rejected.
     *
     * closes_at is the UTC instant at which the operational date ends in the
     * operational timezone. Reports use it to read the status "at close of day"
     * with plain, portable SQL.
     */
    public function up(): void
    {
        Schema::create('activity_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_definition_id')->constrained()->restrictOnDelete();
            $table->date('operational_date');
            $table->string('status', 16)->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('origin', 16)->default('schedule');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('closes_at');
            $table->unsignedBigInteger('latest_update_id')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('requires_follow_up')->default(false);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            // One instance per definition per operational date: this is what
            // makes daily generation idempotent.
            $table->unique(['activity_definition_id', 'operational_date'], 'activity_instances_definition_date_unique');
            $table->index(['operational_date', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_instances');
    }
};
