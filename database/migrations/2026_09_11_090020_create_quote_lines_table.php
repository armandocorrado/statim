<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `description` è testo libero congelato al momento della generazione
     * (es. "Otturazione — dente 16") — Core non modella mai i denti come
     * colonna strutturata, quella conoscenza resta nel verticale che ha
     * composto il testo. `source_treatment_plan_item_id` è, come su
     * `quotes`, un riferimento opaco senza FK verso una tabella Dental.
     * `service_catalog_item_id` è invece una FK reale: è Core↔Core, nessun
     * confine da rispettare.
     */
    public function up(): void
    {
        Schema::create('quote_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->index();
            $table->foreignUlid('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('service_catalog_item_id')->nullable()->constrained()->nullOnDelete();

            $table->string('source_treatment_plan_item_id')->nullable();

            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2);

            // Sconto in percentuale — un unico meccanismo, non anche un
            // importo fisso alternativo, per evitare l'ambiguità di quale
            // dei due vince se entrambi fossero valorizzati.
            $table->decimal('discount_percent', 5, 2)->nullable();

            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->string('vat_exemption_reason')->nullable();

            $table->decimal('line_total', 10, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'quote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_lines');
    }
};
