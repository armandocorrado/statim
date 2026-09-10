<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only, come dental_diary_entries: uno stato dentale non si
     * corregge, si registra un nuovo evento. Lo stato "corrente" di un
     * dente è derivato (il record più recente per quel tooth_number), mai
     * salvato — vedi ToothConditionResolver.
     */
    public function up(): void
    {
        Schema::create('dental_tooth_conditions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('patient_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('operator_id')->constrained('users')->restrictOnDelete();

            // Notazione FDI/ISO 3950 a due cifre — vedi FdiToothNumbers.
            // Non FK verso una tabella: è uno standard fisso, validato in
            // StoreDentalToothConditionRequest.
            $table->string('tooth_number', 2);
            $table->string('condition_type');
            $table->date('recorded_date');

            // Collega l'intervento alla nota di diario della stessa seduta,
            // se registrata — nullable perché uno stato può essere
            // annotato senza una nota di diario associata (es. rilevazione
            // durante un controllo). dental_diary_entries è append-only,
            // mai cancellata: restrictOnDelete coerente con le altre FK.
            $table->foreignUlid('diary_entry_id')->nullable()
                ->constrained('dental_diary_entries')->restrictOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Copre sia il filtro per paziente sia la risoluzione dello
            // stato corrente per dente (ORDER BY recorded_date/id su
            // questo stesso set di colonne).
            $table->index(['tenant_id', 'patient_id', 'tooth_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_tooth_conditions');
    }
};
