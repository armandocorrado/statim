<?php

namespace App\Providers;

use App\Core\Agenda\Models\Appointment;
use App\Core\Agenda\Policies\AppointmentPolicy;
use App\Core\Consents\Listeners\EnforceConsentGate;
use App\Core\Consents\Models\Consent;
use App\Core\Consents\Policies\ConsentPolicy;
use App\Core\Patients\Models\Patient;
use App\Core\Patients\Policies\PatientPolicy;
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
        //
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

        Event::listen(NotificationSending::class, EnforceConsentGate::class);
    }
}
