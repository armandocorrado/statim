<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_document_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->index();
            $table->foreignUlid('billing_document_id')->constrained()->cascadeOnDelete();

            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2);

            // Aliquota IVA: null/0 = esente. Nessuna aliquota precompilata
            // a livello di schema — vedi CLAUDE.md, da validare con un
            // commercialista.
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->string('vat_exemption_reason')->nullable();

            $table->decimal('line_total', 10, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'billing_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_document_lines');
    }
};
