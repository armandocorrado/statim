<?php

namespace App\Http\Controllers;

use App\Core\Agenda\Enums\AppointmentStatus;
use App\Core\Agenda\Models\Appointment;
use App\Core\Billing\Enums\BillingDocumentStatus;
use App\Core\Billing\Models\BillingDocument;
use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Enums\QuoteStatus;
use App\Core\Quotes\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/dashboard` è unica per tutti i ruoli: solo chi ha `dashboard.admin.view`
 * (oggi solo `admin`, vedi TenantRoleProvisioner) riceve la dashboard
 * direzionale con i grafici di sintesi. Gli altri ruoli restano sulla
 * dashboard generica — non è una rotta separata per non rompere la
 * navigazione esistente prima che le altre dashboard per ruolo esistano.
 *
 * Tutti i grafici sono a grana giornaliera/mensile: il periodo (3/6/12
 * mesi, giorno/settimana) è un filtro puramente client-side sugli stessi
 * dati — niente round-trip extra per uno slider, vedi Dashboard/Admin.jsx.
 *
 * La riga di card in cima (colpo d'occhio immediato) riusa gli stessi dati
 * dei grafici sotto dove possibile (il fatturato del mese è l'ultimo punto
 * di `monthlyRevenue`, il tasso di accettazione è lo stesso di
 * `quoteAcceptance`) — un solo calcolo, mai due fonti che potrebbero
 * disallinearsi.
 */
class DashboardController extends Controller
{
    private const REVENUE_MONTHS = 12;

    private const APPOINTMENTS_DAYS = 60;

    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->can('dashboard.admin.view')) {
            return Inertia::render('Dashboard');
        }

        $monthlyRevenue = $this->monthlyRevenueSeries();
        $quoteAcceptance = $this->quoteAcceptanceBreakdown();

        return Inertia::render('Dashboard/Admin', [
            'summary' => [
                'todayAppointments' => $this->todayAppointmentsCount(),
                'quoteAcceptanceRate' => $quoteAcceptance['rate'],
                'monthlyRevenue' => end($monthlyRevenue)['total'],
                'activePatients' => Patient::query()->where('is_active', true)->count(),
            ],
            'monthlyRevenue' => $monthlyRevenue,
            'appointmentsDaily' => $this->appointmentsDailySeries(),
            'quoteAcceptance' => $quoteAcceptance,
            'appointmentsByType' => $this->appointmentsByType(),
        ]);
    }

    /**
     * Appuntamenti di oggi esclusi gli annullati — uno slot annullato non è
     * "qualcosa che succede oggi". Stessa domanda della card originale
     * pre-grafici, ora ridotta al solo numero (il dettaglio orario vive nel
     * grafico "Appuntamenti" sotto, niente doppione).
     */
    private function todayAppointmentsCount(): int
    {
        $todayStart = CarbonImmutable::now()->startOfDay();
        $todayEnd = $todayStart->addDay();

        return Appointment::query()
            ->where('start_at', '<', $todayEnd)
            ->where('end_at', '>', $todayStart)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->count();
    }

    /**
     * Fatturato mensile per gli ultimi REVENUE_MONTHS mesi (incluso quello
     * corrente) — solo documenti Issued, somma di total_amount congelato
     * all'emissione (mai le bozze: il loro importo può ancora cambiare).
     * Mesi senza documenti restano a 0, non vengono omessi — altrimenti il
     * grafico avrebbe un buco silenzioso invece di un vero zero.
     *
     * @return list<array{month: string, total: float}>
     */
    private function monthlyRevenueSeries(): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(self::REVENUE_MONTHS - 1);

        $documents = BillingDocument::query()
            ->where('status', BillingDocumentStatus::Issued->value)
            ->where('issued_at', '>=', $start->toDateString())
            ->get(['issued_at', 'total_amount']);

        $buckets = [];
        for ($i = 0; $i < self::REVENUE_MONTHS; $i++) {
            $key = $start->addMonths($i)->format('Y-m');
            $buckets[$key] = ['month' => $key, 'total' => 0.0];
        }

        foreach ($documents as $document) {
            $key = CarbonImmutable::parse($document->issued_at)->format('Y-m');

            if (isset($buckets[$key])) {
                $buckets[$key]['total'] += (float) $document->total_amount;
            }
        }

        return array_values(array_map(
            fn ($bucket) => ['month' => $bucket['month'], 'total' => round($bucket['total'], 2)],
            $buckets,
        ));
    }

    /**
     * Conteggio giornaliero di appuntamenti (esclusi gli annullati — uno
     * slot annullato libera l'orario, non è "qualcosa che è successo quel
     * giorno") per gli ultimi APPOINTMENTS_DAYS giorni. Raggruppamento in
     * PHP, non con funzioni data SQL specifiche di un motore — query
     * portabile fra MySQL (produzione) e SQLite (test), stessa convenzione
     * di PatientController::index().
     *
     * @return list<array{date: string, count: int}>
     */
    private function appointmentsDailySeries(): array
    {
        $start = CarbonImmutable::now()->startOfDay()->subDays(self::APPOINTMENTS_DAYS - 1);

        $appointments = Appointment::query()
            ->where('start_at', '>=', $start)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->get(['start_at']);

        $buckets = [];
        for ($i = 0; $i < self::APPOINTMENTS_DAYS; $i++) {
            $date = $start->addDays($i)->toDateString();
            $buckets[$date] = ['date' => $date, 'count' => 0];
        }

        foreach ($appointments as $appointment) {
            $date = $appointment->start_at->toDateString();

            if (isset($buckets[$date])) {
                $buckets[$date]['count']++;
            }
        }

        return array_values($buckets);
    }

    /**
     * Stessa identica domanda di QuoteController::index(), scomposta in 3
     * secchi invece del solo tasso: accettati (Accepted+InProgress+
     * Completed), rifiutati, in attesa di risposta (Issued). Una bozza
     * non è mai stata proposta al paziente, non entra nel denominatore.
     *
     * @return array{accepted: int, rejected: int, pending: int, issued: int, rate: ?float}
     */
    private function quoteAcceptanceBreakdown(): array
    {
        $accepted = Quote::query()->whereIn('status', [
            QuoteStatus::Accepted->value, QuoteStatus::InProgress->value, QuoteStatus::Completed->value,
        ])->count();
        $rejected = Quote::query()->where('status', QuoteStatus::Rejected->value)->count();
        $pending = Quote::query()->where('status', QuoteStatus::Issued->value)->count();
        $issued = $accepted + $rejected + $pending;

        return [
            'accepted' => $accepted,
            'rejected' => $rejected,
            'pending' => $pending,
            'issued' => $issued,
            'rate' => $issued > 0 ? round($accepted / $issued * 100, 1) : null,
        ];
    }

    /**
     * Distribuzione appuntamenti per tipo (AppointmentType.name) — esclusi
     * gli annullati, stesso principio del conteggio giornaliero. Join
     * invece di caricare tutto in memoria: solo conteggi aggregati,
     * nessun dato paziente coinvolto.
     *
     * @return list<array{name: string, total: int}>
     */
    private function appointmentsByType(): array
    {
        return Appointment::query()
            ->join('appointment_types', 'appointment_types.id', '=', 'appointments.appointment_type_id')
            ->where('appointments.status', '!=', AppointmentStatus::Cancelled->value)
            ->selectRaw('appointment_types.name as name, count(*) as total')
            ->groupBy('appointment_types.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total])
            ->values()
            ->all();
    }
}
