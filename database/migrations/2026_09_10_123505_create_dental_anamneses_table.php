<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('dental_anamneses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('patient_id')->constrained()->restrictOnDelete();

            // Cifrati (contenuto clinico) — non sezionati: riguardano
            // chiunque tratti il paziente, non solo chi fa igiene.
            $table->text('pathologies')->nullable();
            $table->text('medications')->nullable();
            $table->text('risk_factors')->nullable();
            $table->text('notes')->nullable();

            $table->foreignUlid('updated_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->unique('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_anamneses');
    }
};
