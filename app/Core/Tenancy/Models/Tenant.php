<?php

namespace App\Core\Tenancy\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'database_name', 'vat_number', 'email', 'phone', 'is_active'])]
class Tenant extends Model
{
    use HasFactory, HasUlids;

    /**
     * Registro centrale, non il DB dello studio: vive sempre su 'central',
     * mai sulla connessione dinamica 'tenant'.
     */
    protected $connection = 'central';

    protected static function newFactory(): Factory
    {
        return TenantFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
