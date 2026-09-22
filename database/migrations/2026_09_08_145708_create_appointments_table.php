<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->index();

            // Nullable: un appuntamento senza paziente è un blocco/indisponibilità
            // dell'operatore (pausa, ferie), non un vero appuntamento clinico.
            $table->foreignUlid('patient_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('operator_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('appointment_type_id')->nullable()->constrained()->nullOnDelete();

            // dateTime, non timestamp: MySQL con NO_ZERO_DATE assegna
            // implicitamente DEFAULT CURRENT_TIMESTAMP alla prima colonna
            // TIMESTAMP NOT NULL senza default della tabella, ma rifiuta con
            // "Invalid default value" ogni successiva — start_at/end_at sono
            // entrambe NOT NULL senza default. dateTime non ha questa
            // limitazione legacy.
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('status');

            $table->text('notes')->nullable();

            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'operator_id', 'start_at']);
            $table->index(['tenant_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
