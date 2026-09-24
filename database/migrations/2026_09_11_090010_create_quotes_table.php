<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Preventivo — CORE trasversale (economico/amministrativo), non
     * importa mai nulla dal verticale Dental. `source_treatment_plan_id`
     * è un riferimento OPACO (stringa, nessuna FK) al piano di cura che
     * ha generato questo preventivo: Core lo porta con sé ma non lo
     * interpreta né lo vincola a livello di schema — è il verticale
     * (che può dipendere da Core) a definire la relazione nel verso
     * permesso, vedi DentalTreatmentPlan::quotes(). Stesso principio di
     * TenantRoleProvisioner::extend().
     */
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('patient_id')->constrained()->restrictOnDelete();

            $table->string('source_treatment_plan_id')->nullable();

            $table->string('status');
            $table->date('issued_at')->nullable();

            // Un'unica data: copre sia l'accettazione sia il rifiuto (stati
            // mutuamente esclusivi da Issued) — non due colonne separate.
            $table->date('responded_at')->nullable();

            // Congelati solo all'emissione, mai ricalcolati live da quel
            // momento — stesso principio di BillingDocument.
            $table->decimal('total_taxable', 10, 2)->nullable();
            $table->decimal('total_vat', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();

            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index('source_treatment_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
