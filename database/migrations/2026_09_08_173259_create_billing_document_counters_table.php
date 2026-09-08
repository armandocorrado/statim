<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabella contatore dedicata, non un MAX(document_number)+1 al volo:
     * una riga per tenant+anno che esiste SEMPRE una volta inizializzata,
     * così un SELECT ... FOR UPDATE ha sempre qualcosa da bloccare — a
     * differenza del controllo sovrapposizioni dell'Agenda, dove lockare
     * un set di righe potenzialmente vuoto (nessun appuntamento ancora in
     * quello slot) non protegge dal primo inserimento concorrente. Per la
     * numerazione fiscale (conseguenze legali, non solo un doppio
     * appuntamento) serve la garanzia più solida.
     */
    public function up(): void
    {
        Schema::create('billing_document_counters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_number')->default(1);

            $table->timestamps();

            $table->unique(['tenant_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_document_counters');
    }
};
