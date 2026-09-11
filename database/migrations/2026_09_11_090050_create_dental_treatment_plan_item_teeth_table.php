<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stesso pattern esatto di dental_document_teeth: il "dente" non è
     * mai una riga di una tabella propria, solo un codice FDI — quindi
     * one-to-many da dental_treatment_plan_items, non un many-to-many
     * verso un'entità "dente" inesistente. A differenza di
     * dental_document_teeth (immutabile dopo l'upload), qui le righe
     * SONO sostituibili: seguono la mutabilità della voce di piano a cui
     * appartengono (cascadeOnDelete + sostituzione in blocco a ogni
     * salvataggio della voce).
     */
    public function up(): void
    {
        Schema::create('dental_treatment_plan_item_teeth', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('treatment_plan_item_id')->constrained('dental_treatment_plan_items')->cascadeOnDelete();

            $table->string('tooth_number', 2);

            $table->timestamps();

            // Nome esplicito: quello auto-generato da Laravel supera il
            // limite di 64 caratteri di MySQL per gli identificatori.
            $table->unique(['treatment_plan_item_id', 'tooth_number'], 'dental_tp_item_teeth_item_tooth_unique');
            $table->index(['tenant_id', 'tooth_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_treatment_plan_item_teeth');
    }
};
