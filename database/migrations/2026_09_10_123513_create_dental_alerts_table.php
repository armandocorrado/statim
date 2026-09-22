<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dental_alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->index();
            $table->foreignUlid('patient_id')->constrained()->restrictOnDelete();

            $table->string('category');
            $table->text('description');
            $table->boolean('is_active')->default(true);

            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'patient_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_alerts');
    }
};
