<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A differenza del resto della cartella clinica (diario, documenti,
     * stati dentali), le voci del piano di cura NON sono append-only: un
     * piano è un documento di lavoro in bozza, si aggiunge/toglie una
     * voce mentre si valuta il da farsi — la modifica resta comunque
     * tracciata dal diff generico di Auditable. Sostituite in blocco a
     * ogni salvataggio (stesso pattern di BillingDocumentLine), non
     * un'API di CRUD per singola voce.
     */
    public function up(): void
    {
        Schema::create('dental_treatment_plan_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->index();
            $table->foreignUlid('treatment_plan_id')->constrained('dental_treatment_plans')->cascadeOnDelete();
            $table->foreignUlid('service_catalog_item_id')->constrained()->restrictOnDelete();

            $table->decimal('quantity', 10, 2)->default(1);
            $table->text('notes')->nullable();

            // Raggruppamento libero (es. "Seduta 1") — non un catalogo
            // fisso, puramente descrittivo per-piano.
            $table->string('session_group')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'treatment_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_treatment_plan_items');
    }
};
