<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Tabella contatore dedicata, non un MAX(document_number)+1 al volo:
     * una riga per anno (un database per studio, non serve più scopare per
     * tenant) che esiste SEMPRE una volta inizializzata,
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
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_number')->default(1);

            $table->timestamps();

            $table->unique(['year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_document_counters');
    }
};
