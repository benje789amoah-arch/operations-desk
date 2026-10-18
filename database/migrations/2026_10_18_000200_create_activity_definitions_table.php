<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An activity definition describes a check the desk performs (for example
     * "Daily SMS count in comparison to SMS count from logs") and how often it
     * recurs. Definitions are archived, never deleted, so the instances and
     * updates that reference them always resolve.
     */
    public function up(): void
    {
        Schema::create('activity_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('priority', 16)->default('normal');
            $table->string('recurrence', 16)->default('daily')->index();
            $table->json('recurrence_days')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->time('due_time')->nullable();
            $table->foreignId('default_assignee_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_definitions');
    }
};
