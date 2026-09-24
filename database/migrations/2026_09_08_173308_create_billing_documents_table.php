<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('billing_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('patient_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('recipient_patient_id')->nullable()->constrained('patients')->restrictOnDelete();

            // Copia congelata dei dati fiscali del destinatario al momento
            // della creazione — non un join live su Patient, altrimenti
            // una correzione futura all'anagrafica cambierebbe
            // silenziosamente una fattura già emessa. Cifrati come i campi
            // equivalenti su Patient.
            $table->text('recipient_name')->nullable();
            $table->text('recipient_fiscal_code')->nullable();
            $table->text('recipient_vat_number')->nullable();
            $table->text('recipient_address_street')->nullable();
            $table->string('recipient_address_postal_code')->nullable();
            $table->string('recipient_address_city')->nullable();
            $table->string('recipient_address_province', 2)->nullable();

            $table->string('status');

            // Nullable finché bozza: assegnati solo all'emissione, mai
            // prima — vedi BillingDocumentNumberer.
            $table->unsignedInteger('document_number')->nullable();
            $table->unsignedSmallInteger('document_year')->nullable();
            $table->date('issued_at')->nullable();

            $table->string('fiscal_channel')->nullable();
            $table->string('external_reference')->nullable();

            // Congelati all'emissione (mai ricalcolati live da quel
            // momento) — un futuro bugfix al calcolo dei totali non deve
            // cambiare retroattivamente l'importo di una fattura emessa.
            $table->decimal('total_taxable', 10, 2)->nullable();
            $table->decimal('total_vat', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();

            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->unique(['document_year', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_documents');
    }
};
