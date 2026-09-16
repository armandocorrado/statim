<?php

namespace Database\Seeders;

use App\Core\Agenda\Enums\AppointmentStatus;
use App\Core\Agenda\Models\Appointment;
use App\Core\Agenda\Models\AppointmentType;
use App\Core\Billing\Enums\BillingDocumentStatus;
use App\Core\Billing\Enums\FiscalChannel;
use App\Core\Billing\Models\BillingDocument;
use App\Core\Billing\Support\BillingDocumentNumberer;
use App\Core\Billing\Support\BillingDocumentTotalsCalculator;
use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Enums\QuoteStatus;
use App\Core\Quotes\Models\Quote;
use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Storico demo su alcuni mesi passati — senza questo, i grafici della
 * dashboard admin (fatturato mensile, appuntamenti nel tempo, tasso di
 * accettazione) non hanno nulla da raccontare: un tenant appena seedato
 * ha solo gli appuntamenti di oggi/domani di DemoAppointmentSeeder e
 * zero fatture/preventivi. Deve girare DOPO DemoAppointmentSeeder — crea
 * solo appuntamenti nel passato (mai oggi/futuro), così non interferisce
 * con la sua idempotenza ("skip se l'operatore ha già un appuntamento").
 *
 * Idempotente: salta un tenant che ha già un documento di fatturazione
 * emesso più vecchio di 2 mesi — nessun'altra passata di seeding
 * manuale/demo backdata così tanto, quindi è un marcatore sicuro.
 */
class DemoHistoricalDataSeeder extends Seeder
{
    /**
     * Quanti mesi indietro costruiamo lo storico (incluso il mese
     * corrente, troncato a ieri per non toccare gli appuntamenti
     * odierni/futuri di DemoAppointmentSeeder).
     */
    private const HISTORY_MONTHS = 12;

    private const TARGET_PATIENT_COUNT = 24;

    public function run(): void
    {
        $this->seedForTenant('studio-rossi');
        $this->seedForTenant('studio-bianchi');
    }

    private function seedForTenant(string $slug): void
    {
        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            return;
        }

        if ($this->alreadySeeded($tenant)) {
            return;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $admin = User::where('tenant_id', $tenant->id)->role('admin')->first();
        $odontoiatri = User::where('tenant_id', $tenant->id)->role('odontoiatra')->orderBy('name')->get();

        if (! $admin || $odontoiatri->isEmpty()) {
            return;
        }

        $patients = $this->ensurePatientPool($tenant, $admin);
        $appointmentTypes = AppointmentType::where('tenant_id', $tenant->id)->get();
        $serviceCatalogItems = ServiceCatalogItem::where('tenant_id', $tenant->id)->where('is_active', true)->get();

        if ($patients->isEmpty() || $serviceCatalogItems->isEmpty()) {
            return;
        }

        $today = CarbonImmutable::now()->startOfDay();

        foreach ($this->monthWindows($today) as $index => $window) {
            // Trend leggermente crescente mese su mese — non un piatto
            // "stesso numero ogni mese", altrimenti il grafico non
            // racconterebbe nulla.
            $growth = 0.7 + ($index / (self::HISTORY_MONTHS - 1)) * 0.6;

            $this->seedAppointmentsForMonth($tenant, $admin, $odontoiatri, $patients, $appointmentTypes, $window, $growth);
            $this->seedBillingDocumentsForMonth($tenant, $admin, $patients, $serviceCatalogItems, $window, $growth);
            $this->seedQuotesForMonth($tenant, $admin, $patients, $serviceCatalogItems, $window, $growth);
        }
    }

    private function alreadySeeded(Tenant $tenant): bool
    {
        return BillingDocument::where('tenant_id', $tenant->id)
            ->whereDate('issued_at', '<', CarbonImmutable::now()->subMonths(2)->toDateString())
            ->exists();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Patient>
     */
    private function ensurePatientPool(Tenant $tenant, User $admin): \Illuminate\Support\Collection
    {
        $existing = Patient::where('tenant_id', $tenant->id)->get();
        $missing = self::TARGET_PATIENT_COUNT - $existing->count();

        if ($missing > 0) {
            Patient::factory($missing)->create([
                'tenant_id' => $tenant->id,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        }

        return Patient::where('tenant_id', $tenant->id)->get();
    }

    /**
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function monthWindows(CarbonImmutable $today): array
    {
        $windows = [];
        $yesterday = $today->subDay()->endOfDay();

        for ($i = self::HISTORY_MONTHS - 1; $i >= 0; $i--) {
            $monthStart = $today->startOfMonth()->subMonths($i);
            $monthEnd = $monthStart->endOfMonth();

            if ($monthEnd->greaterThan($yesterday)) {
                $monthEnd = $yesterday;
            }

            $windows[] = ['start' => $monthStart, 'end' => $monthEnd];
        }

        return $windows;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $odontoiatri
     * @param  \Illuminate\Support\Collection<int, Patient>  $patients
     * @param  \Illuminate\Support\Collection<int, AppointmentType>  $appointmentTypes
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $window
     */
    private function seedAppointmentsForMonth(
        Tenant $tenant,
        User $admin,
        \Illuminate\Support\Collection $odontoiatri,
        \Illuminate\Support\Collection $patients,
        \Illuminate\Support\Collection $appointmentTypes,
        array $window,
        float $growth,
    ): void {
        // Statisticamente plausibile, non uno storico clinico vero: la
        // maggior parte degli slot passati è "Completato", una minoranza
        // annullata/non presentata — niente sovrapposizioni verificate
        // (il controllo anti-sovrapposizione vive nel FormRequest/
        // controller HTTP, non a livello di modello, quindi qui non si
        // applica — accettabile per dati demo).
        $statusWeights = [
            AppointmentStatus::Completed, AppointmentStatus::Completed, AppointmentStatus::Completed,
            AppointmentStatus::Completed, AppointmentStatus::Completed, AppointmentStatus::Completed,
            AppointmentStatus::Cancelled, AppointmentStatus::NoShow,
        ];
        $slotHours = [9, 10, 11, 14, 15, 16, 17];

        $day = $window['start'];

        while ($day->lessThanOrEqualTo($window['end'])) {
            if ($day->isWeekend()) {
                $day = $day->addDay();

                continue;
            }

            foreach ($odontoiatri as $operator) {
                $perDay = max(1, (int) round(3 * $growth) + fake()->numberBetween(-1, 1));

                foreach (range(1, $perDay) as $slotIndex) {
                    $hour = fake()->randomElement($slotHours);

                    $start = $day->setTime($hour, fake()->randomElement([0, 30]));

                    $appointment = new Appointment([
                        'patient_id' => $patients->random()->id,
                        'operator_id' => $operator->id,
                        'appointment_type_id' => $appointmentTypes->isNotEmpty() ? $appointmentTypes->random()->id : null,
                        'start_at' => $start,
                        'end_at' => (clone $start)->addMinutes(30),
                    ]);
                    $appointment->tenant_id = $tenant->id;
                    $appointment->status = fake()->randomElement($statusWeights);
                    $appointment->created_by = $admin->id;
                    $appointment->timestamps = false;
                    $appointment->created_at = $start;
                    $appointment->updated_at = $start;
                    $appointment->save();
                }
            }

            $day = $day->addDay();
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Patient>  $patients
     * @param  \Illuminate\Support\Collection<int, ServiceCatalogItem>  $serviceCatalogItems
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $window
     */
    private function seedBillingDocumentsForMonth(
        Tenant $tenant,
        User $admin,
        \Illuminate\Support\Collection $patients,
        \Illuminate\Support\Collection $serviceCatalogItems,
        array $window,
        float $growth,
    ): void {
        $count = (int) round(18 * $growth);

        foreach (range(1, $count) as $i) {
            $issuedAt = $this->randomWeekdayIn($window);
            $patient = $patients->random();
            $lines = $this->randomServiceLines($serviceCatalogItems);

            DB::transaction(function () use ($tenant, $admin, $patient, $lines, $issuedAt) {
                $document = new BillingDocument(['patient_id' => $patient->id]);
                $document->tenant_id = $tenant->id;
                $document->status = BillingDocumentStatus::Draft;
                $document->created_by = $admin->id;
                $document->recipient_name = "{$patient->first_name} {$patient->last_name}";
                $document->recipient_fiscal_code = $patient->fiscal_code;
                $document->recipient_address_street = $patient->address_street;
                $document->recipient_address_postal_code = $patient->address_postal_code;
                $document->recipient_address_city = $patient->address_city;
                $document->recipient_address_province = $patient->address_province;
                $document->timestamps = false;
                $document->created_at = $issuedAt;
                $document->updated_at = $issuedAt;
                $document->save();

                foreach ($lines as $sortOrder => $line) {
                    $newLine = $document->lines()->make([
                        'description' => $line['description'],
                        'quantity' => 1,
                        'unit_price' => $line['unit_price'],
                        'vat_rate' => null,
                        'vat_exemption_reason' => 'art. 10 n. 18 DPR 633/72',
                        'sort_order' => $sortOrder,
                    ]);
                    $newLine->line_total = round($line['unit_price'], 2);
                    $newLine->tenant_id = $tenant->id;
                    $newLine->save();
                }

                $totals = BillingDocumentTotalsCalculator::calculate($document->lines->map(fn ($line) => [
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'vat_rate' => $line->vat_rate,
                ]));

                $document->document_number = BillingDocumentNumberer::next($tenant, (int) $issuedAt->format('Y'));
                $document->document_year = (int) $issuedAt->format('Y');
                $document->issued_at = $issuedAt->toDateString();
                $document->total_taxable = $totals['taxable'];
                $document->total_vat = $totals['vat'];
                $document->total_amount = $totals['total'];
                $document->fiscal_channel = FiscalChannel::SistemaTs;
                $document->external_reference = 'DEMO-'.$document->id;
                $document->status = BillingDocumentStatus::Issued;
                $document->timestamps = false;
                $document->save();
            });
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Patient>  $patients
     * @param  \Illuminate\Support\Collection<int, ServiceCatalogItem>  $serviceCatalogItems
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $window
     */
    private function seedQuotesForMonth(
        Tenant $tenant,
        User $admin,
        \Illuminate\Support\Collection $patients,
        \Illuminate\Support\Collection $serviceCatalogItems,
        array $window,
        float $growth,
    ): void {
        $count = (int) round(9 * $growth);

        foreach (range(1, $count) as $i) {
            $issuedAt = $this->randomWeekdayIn($window);
            $patient = $patients->random();
            $lines = $this->randomServiceLines($serviceCatalogItems, $serviceCatalogItems->first());

            $quote = new Quote(['patient_id' => $patient->id]);
            $quote->tenant_id = $tenant->id;
            $quote->status = QuoteStatus::Draft;
            $quote->created_by = $admin->id;
            $quote->timestamps = false;
            $quote->created_at = $issuedAt;
            $quote->updated_at = $issuedAt;
            $quote->save();

            foreach ($lines as $sortOrder => $line) {
                $newLine = $quote->lines()->make([
                    'service_catalog_item_id' => $line['service_catalog_item_id'],
                    'description' => $line['description'],
                    'quantity' => 1,
                    'unit_price' => $line['unit_price'],
                    'vat_rate' => null,
                    'vat_exemption_reason' => 'art. 10 n. 18 DPR 633/72',
                    'sort_order' => $sortOrder,
                ]);
                $newLine->line_total = round($line['unit_price'], 2);
                $newLine->tenant_id = $tenant->id;
                $newLine->save();
            }

            $totals = BillingDocumentTotalsCalculator::calculate($quote->lines->map(fn ($line) => [
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'vat_rate' => $line->vat_rate,
            ]));

            $quote->status = QuoteStatus::Issued;
            $quote->issued_at = $issuedAt->toDateString();
            $quote->total_taxable = $totals['taxable'];
            $quote->total_vat = $totals['vat'];
            $quote->total_amount = $totals['total'];
            $quote->timestamps = false;
            $quote->save();

            // ~78% accettazione (Accepted/InProgress/Completed contro
            // Rejected) fra i preventivi che hanno già avuto risposta —
            // una parte resta "Issued" (in attesa), plausibile per i mesi
            // più recenti. Vedi CLAUDE.md, tasso di accettazione.
            $roll = fake()->numberBetween(1, 100);
            $respondedAt = $issuedAt->addDays(fake()->numberBetween(1, 10));

            if ($roll <= 55) {
                $this->transitionQuote($quote, QuoteStatus::Accepted, $respondedAt);
                $this->maybeAdvanceToCompleted($quote, $respondedAt);
            } elseif ($roll <= 78) {
                $this->transitionQuote($quote, QuoteStatus::Accepted, $respondedAt);
                $quote->status = QuoteStatus::InProgress;
                $quote->timestamps = false;
                $quote->save();
            } elseif ($roll <= 95) {
                $this->transitionQuote($quote, QuoteStatus::Rejected, $respondedAt);
            }
            // il resto (5%) resta "Issued": preventivo ancora in attesa di risposta.
        }
    }

    private function transitionQuote(Quote $quote, QuoteStatus $status, CarbonImmutable $respondedAt): void
    {
        $quote->status = $status;
        $quote->responded_at = $respondedAt->toDateString();
        $quote->timestamps = false;
        $quote->save();
    }

    private function maybeAdvanceToCompleted(Quote $quote, CarbonImmutable $respondedAt): void
    {
        if (fake()->boolean(60)) {
            $quote->status = QuoteStatus::Completed;
            $quote->timestamps = false;
            $quote->save();
        }
    }

    /**
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $window
     */
    private function randomWeekdayIn(array $window): CarbonImmutable
    {
        $days = max(1, $window['start']->diffInDays($window['end']));

        do {
            $candidate = $window['start']->addDays(fake()->numberBetween(0, $days));
        } while ($candidate->isWeekend());

        return $candidate;
    }

    /**
     * 1-3 prestazioni casuali dal listino del tenant, con una piccola
     * variazione di prezzo (±10%) — altrimenti ogni fattura/preventivo
     * con la stessa prestazione avrebbe l'identico importo, poco
     * credibile per un grafico di andamento.
     *
     * @param  \Illuminate\Support\Collection<int, ServiceCatalogItem>  $serviceCatalogItems
     * @return list<array{service_catalog_item_id: string, description: string, unit_price: float}>
     */
    private function randomServiceLines(\Illuminate\Support\Collection $serviceCatalogItems, ?ServiceCatalogItem $forceFirst = null): array
    {
        $lineCount = fake()->numberBetween(1, 3);

        if ($forceFirst) {
            $rest = $serviceCatalogItems->reject(fn (ServiceCatalogItem $item) => $item->id === $forceFirst->id);
            $extra = min($lineCount - 1, $rest->count());
            $items = collect([$forceFirst])->merge($extra > 0 ? $rest->random($extra) : collect());
        } else {
            $items = $serviceCatalogItems->random(min($lineCount, $serviceCatalogItems->count()));
        }

        return $items->map(function (ServiceCatalogItem $item) {
            $variance = fake()->numberBetween(-10, 10) / 100;

            return [
                'service_catalog_item_id' => $item->id,
                'description' => $item->name,
                'unit_price' => round((float) $item->base_price * (1 + $variance), 2),
            ];
        })->values()->all();
    }
}
