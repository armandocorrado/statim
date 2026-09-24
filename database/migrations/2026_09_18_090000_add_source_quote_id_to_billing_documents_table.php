<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * A differenza di `Quote.source_treatment_plan_id` (riferimento OPACO
     * verso il verticale Dental, nessuna FK) questo è un legame Core↔Core:
     * una FK vera verso `quotes`. Nullable perché la maggior parte dei
     * documenti resta creata a mano; `restrictOnDelete` perché un
     * preventivo con un documento fiscale già generato non deve poter
     * essere eliminato (comunque già impedito: un preventivo si elimina
     * solo in bozza, e da una bozza non si genera ancora nulla).
     */
    public function up(): void
    {
        Schema::table('billing_documents', function (Blueprint $table) {
            $table->foreignUlid('source_quote_id')->nullable()->after('recipient_patient_id')
                ->constrained('quotes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billing_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_quote_id');
        });
    }
};
