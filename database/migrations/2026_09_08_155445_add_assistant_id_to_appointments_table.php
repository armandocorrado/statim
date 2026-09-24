<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Chi assiste alla poltrona per questo appuntamento (in genere
            // un ASO), distinto da operator_id (chi tratta il paziente).
            // Nullable: non ogni appuntamento ha un assistente assegnato.
            $table->foreignUlid('assistant_id')->nullable()->after('operator_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assistant_id');
        });
    }
};
