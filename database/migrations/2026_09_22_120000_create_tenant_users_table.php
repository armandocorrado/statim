<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mappa email->studio per il futuro login (Tappa 2). Vive nel DB centrale:
     * nessun dato clinico, nessun hash password, nessun ruolo.
     */
    protected $connection = 'central';

    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('email');
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->ulid('remote_user_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Composito, non un indice separato su 'email': serve gia' sia il
            // lookup per sola email (colonna piu' a sinistra) sia il vincolo
            // "un'email al massimo una volta per studio".
            $table->unique(['email', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};
