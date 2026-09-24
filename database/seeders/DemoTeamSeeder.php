<?php

namespace Database\Seeders;

use App\Core\Tenancy\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Popola gli operatori clinici demo (4 odontoiatri + 4 igienisti + 4
 * assistenti alla poltrona) sullo studio la cui connessione 'tenant' è già
 * risolta dal chiamante (DatabaseSeeder) — servono abbastanza operatori per
 * esercitare davvero le viste multi-operatore dell'Agenda, non solo un
 * operatore per ruolo. I 4 ASO sono elencati nello stesso ordine dei 4
 * odontoiatri (un assistente "corrispondente" per ciascuno) — non esiste
 * ancora un legame vero e proprio a livello di dati tra operatore e
 * assistente, è solo una convenzione nell'elenco qui sotto.
 *
 * Idempotente: verifica per email (unica globalmente su User) prima di
 * creare, rilanciarlo non produce duplicati né riassegna ruoli già
 * presenti.
 */
class DemoTeamSeeder extends Seeder
{
    /**
     * Stessa password demo di DatabaseSeeder — un'unica credenziale nota
     * per tutto l'ambiente demo, non una seconda convenzione separata.
     */
    private const DEMO_PASSWORD = 'MedCare#Wild2026';

    /**
     * @return list<array{first_name: string, last_name: string, role: string}>
     */
    private function teamMembers(): array
    {
        return [
            ['first_name' => 'Giulia', 'last_name' => 'Ferrari', 'role' => 'odontoiatra'],
            ['first_name' => 'Marco', 'last_name' => 'Esposito', 'role' => 'odontoiatra'],
            ['first_name' => 'Chiara', 'last_name' => 'Ricci', 'role' => 'odontoiatra'],
            ['first_name' => 'Luca', 'last_name' => 'Gallo', 'role' => 'odontoiatra'],
            ['first_name' => 'Sara', 'last_name' => 'Conti', 'role' => 'igienista'],
            ['first_name' => 'Davide', 'last_name' => 'Moretti', 'role' => 'igienista'],
            ['first_name' => 'Elena', 'last_name' => 'Fontana', 'role' => 'igienista'],
            ['first_name' => 'Matteo', 'last_name' => 'Barbieri', 'role' => 'igienista'],
            ['first_name' => 'Francesca', 'last_name' => 'Bruno', 'role' => 'aso'], // con Giulia Ferrari
            ['first_name' => 'Alessandro', 'last_name' => 'Greco', 'role' => 'aso'], // con Marco Esposito
            ['first_name' => 'Martina', 'last_name' => 'Villa', 'role' => 'aso'], // con Chiara Ricci
            ['first_name' => 'Simone', 'last_name' => 'Ferri', 'role' => 'aso'], // con Luca Gallo
        ];
    }

    public function run(string $emailDomain): void
    {
        $tenant = app(\App\Core\Tenancy\Support\TenantConnectionResolver::class)->current();

        foreach ($this->teamMembers() as $member) {
            $email = strtolower("{$member['first_name']}.{$member['last_name']}@{$emailDomain}");

            if (User::where('email', $email)->exists()) {
                continue;
            }

            $user = User::factory()->create([
                'name' => "{$member['first_name']} {$member['last_name']}",
                'email' => $email,
                'password' => Hash::make(self::DEMO_PASSWORD),
            ]);

            $user->assignRole($member['role']);

            TenantUser::query()->updateOrCreate(
                ['email' => $email, 'tenant_id' => $tenant->id],
                ['remote_user_id' => $user->id, 'is_active' => true],
            );
        }
    }
}
