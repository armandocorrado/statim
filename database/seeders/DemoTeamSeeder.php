<?php

namespace Database\Seeders;

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Popola gli operatori clinici demo (4 odontoiatri + 4 igienisti + 4
 * assistenti alla poltrona per studio) sui tenant demo — servono
 * abbastanza operatori per esercitare davvero le viste multi-operatore
 * dell'Agenda, non solo un operatore per ruolo. I 4 ASO sono elencati
 * nello stesso ordine dei 4 odontoiatri (un assistente "corrispondente"
 * per ciascuno) — non esiste ancora un legame vero e proprio a livello di
 * dati tra operatore e assistente, è solo una convenzione nell'elenco qui
 * sotto.
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
            ['first_name' => 'Francesca', 'last_name' => 'Bruno', 'role' => 'aso'], // con Giulia Ferrari
            ['first_name' => 'Alessandro', 'last_name' => 'Greco', 'role' => 'aso'], // con Marco Esposito
            ['first_name' => 'Martina', 'last_name' => 'Villa', 'role' => 'aso'], // con Chiara Ricci
            ['first_name' => 'Simone', 'last_name' => 'Ferri', 'role' => 'aso'], // con Luca Gallo
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
