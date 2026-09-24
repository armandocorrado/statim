<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Il piano di cura NON ha uno stato proprio (a differenza del
     * preventivo che genera): è un contenitore di lavoro clinico, non un
     * documento con un ciclo di vita commerciale. Uno stesso piano può
     * generare più preventivi nel tempo (es. una revisione dopo
     * trattativa) — ognuno resta uno snapshot indipendente, vedi CLAUDE.md.
     */
    public function up(): void
    {
        Schema::create('dental_treatment_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('patient_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();

            $table->string('title')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_treatment_plans');
    }
};
