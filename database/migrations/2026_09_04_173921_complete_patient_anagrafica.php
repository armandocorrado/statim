<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->renameColumn('phone', 'mobile_phone');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->string('birth_place')->nullable()->after('date_of_birth');

            // Encrypted at the Eloquent layer (same treatment as mobile_phone).
            $table->text('landline_phone')->nullable()->after('mobile_phone');

            // Replaces the single free-text `address` column with structured
            // fields. Only the street is encrypted — postal_code/city/province
            // stay in plaintext so they remain filterable/queryable in SQL.
            $table->text('address_street')->nullable()->after('address');
            $table->string('address_postal_code')->nullable()->after('address_street');
            $table->string('address_city')->nullable()->after('address_postal_code');
            $table->string('address_province', 2)->nullable()->after('address_city');

            // Residenza anagrafica, valorizzata solo se diversa dal domicilio.
            $table->text('residence_street')->nullable()->after('address_province');
            $table->string('residence_postal_code')->nullable()->after('residence_street');
            $table->string('residence_city')->nullable()->after('residence_postal_code');
            $table->string('residence_province', 2)->nullable()->after('residence_city');

            $table->text('vat_number')->nullable()->after('residence_province');

            $table->string('source')->nullable()->after('vat_number');

            $table->foreignUlid('guardian_patient_id')->nullable()->after('source')
                ->constrained('patients')->nullOnDelete();
            $table->string('guardian_relationship')->nullable()->after('guardian_patient_id');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guardian_patient_id');
            $table->dropColumn([
                'birth_place', 'landline_phone',
                'address_street', 'address_postal_code', 'address_city', 'address_province',
                'residence_street', 'residence_postal_code', 'residence_city', 'residence_province',
                'vat_number', 'source', 'guardian_relationship',
            ]);
            $table->text('address')->nullable();
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->renameColumn('mobile_phone', 'phone');
        });
    }
};
