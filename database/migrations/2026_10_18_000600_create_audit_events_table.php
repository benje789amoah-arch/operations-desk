<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The system audit log, kept separate from user-entered remarks.
     *
     * Each row stores the SHA-256 hash of its own content chained to the hash
     * of the row before it, so any later edit or deletion of a row is
     * detectable by App\Services\AuditLogger::verifyChain(). This is
     * tamper-evidence, not tamper-proofing: protect the database itself too.
     */
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('event', 64)->index();
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->index();
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64)->unique();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
