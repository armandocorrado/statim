<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Append-only: nessuna colonna updated_at usata a scopo applicativo,
     * nessuna rotta di update/delete sul model — una nota di seduta non si
     * corregge, si annota con una nuova voce. Stesso principio di
     * AuditLog/Consent/BillingDocument emesso.
     */
    public function up(): void
    {
        Schema::create('dental_diary_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('patient_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('operator_id')->constrained('users')->restrictOnDelete();

            $table->date('entry_date');
            $table->string('section');
            $table->text('content');

            $table->timestamps();

            $table->index(['patient_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_diary_entries');
    }
};
