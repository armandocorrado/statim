<?php

namespace App\Core\Agenda\Models;

use App\Core\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\AppointmentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'name', 'color', 'is_active'])]
class AppointmentType extends Model
{
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return AppointmentTypeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
