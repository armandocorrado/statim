<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Collega un documento clinico a uno o più denti (notazione FDI) — non
     * un classico many-to-many fra due entità: il "dente" non è mai una
     * riga di una tabella propria (coerente con dental_tooth_conditions),
     * quindi è un one-to-many da dental_documents, non una pivot fra due
     * modelli. Facoltativo: un documento senza righe qui (es. un OPT
     * d'insieme) semplicemente non compare in nessun pannello-dente.
     *
     * Append-only e immutabile: le righe si creano solo al momento
     * dell'upload del documento, mai dopo — nessuna colonna updated_at,
     * nessuna rotta di update/delete, stesso principio di dental_documents
     * stesso.
     */
    public function up(): void
    {
        Schema::create('dental_document_teeth', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->index();
            $table->foreignUlid('document_id')->constrained('dental_documents')->restrictOnDelete();

            // Notazione FDI/ISO 3950 — vedi FdiToothNumbers. Non FK verso
            // una tabella: è uno standard fisso, validato in
            // StoreDentalDocumentRequest.
            $table->string('tooth_number', 2);

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_id', 'tooth_number']);
            $table->index(['tenant_id', 'tooth_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_document_teeth');
    }
};
