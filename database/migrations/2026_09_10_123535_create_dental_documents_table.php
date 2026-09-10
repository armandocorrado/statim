<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nessuna rotta di update/delete: un documento clinico caricato (referto,
     * radiografia, foto) non si sostituisce né si cancella, stesso principio
     * del diario. Il file vive su storage privato (disco `local`, mai
     * raggiungibile via URL pubblico) — qui solo i metadati.
     */
    public function up(): void
    {
        Schema::create('dental_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('patient_id')->constrained()->restrictOnDelete();

            $table->string('section');
            $table->string('document_type');
            $table->text('description')->nullable();

            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedInteger('file_size');

            $table->foreignUlid('uploaded_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'patient_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_documents');
    }
};
