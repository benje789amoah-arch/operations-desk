<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A handover is submitted by the outgoing person for an operational date
     * and shift, then acknowledged by a different, incoming person. The
     * snapshot column freezes the counts and outstanding items as they were
     * at submission so the record still reads correctly after later updates.
     */
    public function up(): void
    {
        Schema::create('handovers', function (Blueprint $table) {
            $table->id();
            $table->date('operational_date');
            $table->string('shift', 16);
            $table->foreignId('outgoing_user_id')->constrained('users')->restrictOnDelete();
            $table->text('outgoing_note');
            $table->timestamp('submitted_at');
            $table->json('snapshot');
            $table->foreignId('incoming_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('acknowledgement_note')->nullable();
            $table->timestamps();

            $table->unique(['operational_date', 'shift']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handovers');
    }
};
