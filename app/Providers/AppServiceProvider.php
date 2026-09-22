<?php

namespace App\Providers;

use App\Core\Agenda\Models\Appointment;
use App\Core\Agenda\Policies\AppointmentPolicy;
use App\Core\Billing\Contracts\DigitalPreservationGateway;
use App\Core\Billing\Contracts\ElectronicInvoiceGateway;
use App\Core\Billing\Contracts\HealthExpenseReportingGateway;
use App\Core\Billing\Gateways\MockDigitalPreservationGateway;
use App\Core\Billing\Gateways\MockElectronicInvoiceGateway;
use App\Core\Billing\Gateways\MockHealthExpenseReportingGateway;
use App\Core\Billing\Models\BillingDocument;
use App\Core\Billing\Policies\BillingDocumentPolicy;
use App\Core\Consents\Listeners\EnforceConsentGate;
use App\Core\Consents\Models\Consent;
use App\Core\Consents\Policies\ConsentPolicy;
use App\Core\Patients\Models\Patient;
use App\Core\Patients\Policies\PatientPolicy;
use App\Core\Quotes\Models\Quote;
use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Core\Quotes\Policies\QuotePolicy;
use App\Core\Quotes\Policies\ServiceCatalogItemPolicy;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use App\Core\Users\Policies\UserPolicy;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Implementazioni mock — vedi CLAUDE.md per cosa serve prima di
        // sostituirle con implementazioni reali (certificati, ambienti di
        // test SdI/Sistema TS). Cambiare SOLO questi binding quando quel
        // giorno arriverà: nessun'altra riga del modulo Billing dipende
        // da questi dettagli.
        $this->app->bind(ElectronicInvoiceGateway::class, MockElectronicInvoiceGateway::class);
        $this->app->bind(HealthExpenseReportingGateway::class, MockHealthExpenseReportingGateway::class);
        $this->app->bind(DigitalPreservationGateway::class, MockDigitalPreservationGateway::class);

        // Singleton: la stessa istanza per tutta la request/il comando, cosi'
        // TenantConnectionResolver::current() riflette sempre l'ultimo studio risolto.
        $this->app->singleton(TenantConnectionResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Consent::class, ConsentPolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(BillingDocument::class, BillingDocumentPolicy::class);
        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(ServiceCatalogItem::class, ServiceCatalogItemPolicy::class);

        Event::listen(NotificationSending::class, EnforceConsentGate::class);
    }
}
