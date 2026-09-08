<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('patient_id')->constrained()->restrictOnDelete();

            // Chi ha materialmente espresso il consenso, se diverso dal
            // paziente (tutore di un minore) — vedi App\Core\Consents\Models\Consent.
            $table->foreignUlid('given_by_patient_id')->nullable()->constrained('patients')->nullOnDelete();

            $table->string('purpose');
            $table->string('collection_method');
            $table->string('policy_version');

            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();

            $table->foreignUlid('recorded_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'patient_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
