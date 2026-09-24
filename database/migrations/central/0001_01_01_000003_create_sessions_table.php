<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Infrastruttura trasversale, non dati di uno studio: una sessione
     * esiste PRIMA ancora di sapere quale tenant risolvere (RestoreTenantConnection
     * legge session('tenant_id') per deciderlo) - non puo' vivere sulla
     * connessione dinamica 'tenant' senza creare una dipendenza circolare.
     */
    protected $connection = 'central';

    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->ulid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
