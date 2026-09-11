<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Listino prestazioni per-tenant — profession-agnostic, come
     * AppointmentType: Core non sa cosa significhino i nomi delle voci
     * (es. "Otturazione"), è solo un catalogo di elementi vendibili con un
     * prezzo. `category` è testo libero non interpretato da Core — un
     * verticale (Dental) può usarlo come vuole (es. confrontarlo con
     * DentalRecordSection::Hygiene->value) senza che Core lo sappia.
     */
    public function up(): void
    {
        Schema::create('service_catalog_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();

            $table->decimal('base_price', 10, 2);

            // Esenzione IVA: null/0 = esente, stesso schema di
            // BillingDocumentLine — precompilati come suggerimento quando
            // si genera una riga di preventivo da questa voce, restano
            // modificabili riga per riga.
            $table->decimal('default_vat_rate', 5, 2)->nullable();
            $table->string('default_vat_exemption_reason')->nullable();

            // Predisposto per l'Agenda futura — non collegato ora,
            // nessuna logica applicativa lo legge ancora.
            $table->unsignedInteger('default_duration_minutes')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_catalog_items');
    }
};
