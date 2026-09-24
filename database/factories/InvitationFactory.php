<?php

namespace Database\Factories;

use App\Core\Users\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'email' => fake()->safeEmail(),
            'role' => 'segreteria',
            'token_hash' => hash('sha256', Str::random(40)),
            'invited_by' => User::factory(),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }
}
