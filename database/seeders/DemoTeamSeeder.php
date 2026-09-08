<?php

namespace Database\Seeders;

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Popola gli operatori clinici demo (4 odontoiatri + 4 igienisti per
 * studio) sui tenant demo — servono abbastanza operatori per esercitare
 * davvero le viste multi-operatore dell'Agenda, non solo un odontoiatra e
 * un igienista soli.
 *
 * Idempotente: verifica per email (unica globalmente su User) prima di
 * creare, rilanciarlo non produce duplicati né riassegna ruoli già
 * presenti. Opera solo sui tenant demo noti per slug (studio-rossi/
 * studio-bianchi, creati da DatabaseSeeder) — se non esistono ancora,
 * salta senza errore: non tocca né crea altri tenant.
 */
class DemoTeamSeeder extends Seeder
{
    /**
     * Stessa password demo di DatabaseSeeder — un'unica credenziale nota
     * per tutto l'ambiente demo, non una seconda convenzione separata.
     */
    private const DEMO_PASSWORD = 'medcare!wild';

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
        ];
    }

    public function run(): void
    {
        $this->seedForTenant('studio-rossi', 'rossi.test');
        $this->seedForTenant('studio-bianchi', 'bianchi.test');
    }

    private function seedForTenant(string $slug, string $emailDomain): void
    {
        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            return;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        foreach ($this->teamMembers() as $member) {
            $email = strtolower("{$member['first_name']}.{$member['last_name']}@{$emailDomain}");

            if (User::where('email', $email)->exists()) {
                continue;
            }

            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => "{$member['first_name']} {$member['last_name']}",
                'email' => $email,
                'password' => Hash::make(self::DEMO_PASSWORD),
            ]);

            $user->assignRole($member['role']);
        }
    }
}
