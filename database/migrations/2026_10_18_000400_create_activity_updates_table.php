<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every Done/Pending update is appended here and never edited or deleted.
     *
     * The updater_* columns are a deliberate point-in-time snapshot of the
     * person's details ("bio") when they made the update, so a later change of
     * designation or role does not rewrite history. user_id remains the
     * authoritative reference to the account.
     */
    public function up(): void
    {
        Schema::create('activity_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_instance_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('updater_name');
            $table->string('updater_email');
            $table->string('updater_staff_number', 32)->nullable();
            $table->string('updater_designation', 120)->nullable();
            $table->string('updater_role', 32);
            $table->string('previous_status', 16)->nullable();
            $table->string('new_status', 16);
            $table->text('remark');
            $table->text('supporting_details')->nullable();
            $table->boolean('requires_follow_up')->default(false);
            $table->timestamp('created_at');

            $table->index(['activity_instance_id', 'id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('activity_instances', function (Blueprint $table) {
            $table->foreign('latest_update_id')->references('id')->on('activity_updates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_instances', function (Blueprint $table) {
            $table->dropForeign(['latest_update_id']);
        });

        Schema::dropIfExists('activity_updates');
    }
};
