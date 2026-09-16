<?php

use App\Core\Agenda\Http\Controllers\AgendaController;
use App\Core\Agenda\Http\Controllers\AppointmentController;
use App\Core\Agenda\Http\Controllers\PatientSearchController;
use App\Core\Billing\Http\Controllers\BillingDocumentController;
use App\Core\Consents\Http\Controllers\ConsentController;
use App\Core\Patients\Http\Controllers\PatientController;
use App\Core\Quotes\Http\Controllers\QuoteController;
use App\Core\Quotes\Http\Controllers\ServiceCatalogItemController;
use App\Core\Users\Http\Controllers\InvitationAcceptController;
use App\Core\Users\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Modules\Dental\Http\Controllers\DentalAlertController;
use App\Modules\Dental\Http\Controllers\DentalAnamnesisController;
use App\Modules\Dental\Http\Controllers\DentalClinicalRecordController;
use App\Modules\Dental\Http\Controllers\DentalDiaryEntryController;
use App\Modules\Dental\Http\Controllers\DentalDocumentController;
use App\Modules\Dental\Http\Controllers\DentalOdontogramController;
use App\Modules\Dental\Http\Controllers\DentalTreatmentPlanController;
use App\Modules\Dental\Http\Controllers\DentalTreatmentPlanItemController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'tenant'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::resource('patients', PatientController::class);

    Route::get('agenda', [AgendaController::class, 'index'])->name('agenda.index');
    Route::get('agenda/patients-search', [PatientSearchController::class, 'index'])->name('agenda.patients-search');
    Route::post('agenda/appointments', [AppointmentController::class, 'store'])->name('agenda.appointments.store');
    Route::patch('agenda/appointments/{appointment}', [AppointmentController::class, 'update'])->name('agenda.appointments.update');

    Route::get('billing', [BillingDocumentController::class, 'index'])->name('billing.index');
    Route::get('billing/create', [BillingDocumentController::class, 'create'])->name('billing.create');
    Route::post('billing', [BillingDocumentController::class, 'store'])->name('billing.store');
    Route::get('billing/{document}', [BillingDocumentController::class, 'show'])->name('billing.show');
    Route::get('billing/{document}/edit', [BillingDocumentController::class, 'edit'])->name('billing.edit');
    Route::put('billing/{document}', [BillingDocumentController::class, 'update'])->name('billing.update');
    Route::delete('billing/{document}', [BillingDocumentController::class, 'destroy'])->name('billing.destroy');
    Route::patch('billing/{document}/issue', [BillingDocumentController::class, 'issue'])->name('billing.issue');

    Route::get('quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::get('quotes/{quote}/edit', [QuoteController::class, 'edit'])->name('quotes.edit');
    Route::put('quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
    Route::delete('quotes/{quote}', [QuoteController::class, 'destroy'])->name('quotes.destroy');
    Route::patch('quotes/{quote}/issue', [QuoteController::class, 'issue'])->name('quotes.issue');
    Route::patch('quotes/{quote}/status', [QuoteController::class, 'updateStatus'])->name('quotes.status.update');

    Route::get('service-catalog', [ServiceCatalogItemController::class, 'index'])->name('service-catalog.index');
    Route::post('service-catalog', [ServiceCatalogItemController::class, 'store'])->name('service-catalog.store');
    Route::put('service-catalog/{serviceCatalogItem}', [ServiceCatalogItemController::class, 'update'])->name('service-catalog.update');

    Route::get('patients/{patient}/dental', [DentalClinicalRecordController::class, 'show'])->name('dental.show');
    Route::put('patients/{patient}/dental/anamnesis', [DentalAnamnesisController::class, 'update'])->name('dental.anamnesis.update');
    Route::post('patients/{patient}/dental/alerts', [DentalAlertController::class, 'store'])->name('dental.alerts.store');
    Route::patch('patients/{patient}/dental/alerts/{alert}/resolve', [DentalAlertController::class, 'resolve'])->name('dental.alerts.resolve');
    Route::post('patients/{patient}/dental/diary', [DentalDiaryEntryController::class, 'store'])->name('dental.diary.store');
    Route::post('patients/{patient}/dental/documents', [DentalDocumentController::class, 'store'])->name('dental.documents.store');
    Route::get('patients/{patient}/dental/documents/{document}/download', [DentalDocumentController::class, 'download'])->name('dental.documents.download');
    Route::get('patients/{patient}/dental/documents/{document}/preview', [DentalDocumentController::class, 'preview'])->name('dental.documents.preview');
    Route::get('patients/{patient}/dental/odontogram', [DentalOdontogramController::class, 'show'])->name('dental.odontogram.show');
    Route::post('patients/{patient}/dental/odontogram', [DentalOdontogramController::class, 'store'])->name('dental.odontogram.store');

    Route::get('patients/{patient}/dental/treatment-plans', [DentalTreatmentPlanController::class, 'index'])->name('dental.treatment-plans.index');
    Route::post('patients/{patient}/dental/treatment-plans', [DentalTreatmentPlanController::class, 'store'])->name('dental.treatment-plans.store');
    Route::get('patients/{patient}/dental/treatment-plans/{treatmentPlan}', [DentalTreatmentPlanController::class, 'show'])->name('dental.treatment-plans.show');
    Route::delete('patients/{patient}/dental/treatment-plans/{treatmentPlan}', [DentalTreatmentPlanController::class, 'destroy'])->name('dental.treatment-plans.destroy');
    Route::post('patients/{patient}/dental/treatment-plans/{treatmentPlan}/generate-quote', [DentalTreatmentPlanController::class, 'generateQuote'])->name('dental.treatment-plans.generate-quote');
    Route::post('patients/{patient}/dental/treatment-plans/{treatmentPlan}/items', [DentalTreatmentPlanItemController::class, 'store'])->name('dental.treatment-plans.items.store');
    Route::put('patients/{patient}/dental/treatment-plans/{treatmentPlan}/items/{item}', [DentalTreatmentPlanItemController::class, 'update'])->name('dental.treatment-plans.items.update');
    Route::delete('patients/{patient}/dental/treatment-plans/{treatmentPlan}/items/{item}', [DentalTreatmentPlanItemController::class, 'destroy'])->name('dental.treatment-plans.items.destroy');

    Route::post('patients/{patient}/consents', [ConsentController::class, 'store'])->name('patients.consents.store');
    Route::patch('patients/{patient}/consents/{consent}/revoke', [ConsentController::class, 'revoke'])->name('patients.consents.revoke');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users/invitations', [UserController::class, 'storeInvitation'])->name('users.invitations.store');
    Route::delete('users/invitations/{invitation}', [UserController::class, 'destroyInvitation'])->name('users.invitations.destroy');
    Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role.update');
    Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::patch('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    Route::patch('users/{user}/quote-price-permission', [UserController::class, 'updateQuotePricePermission'])->name('users.quote-price-permission.update');
});

Route::middleware('guest')->group(function () {
    Route::get('invitations/{token}', [InvitationAcceptController::class, 'show'])->name('invitations.accept');
    Route::post('invitations/{token}', [InvitationAcceptController::class, 'store'])->name('invitations.store');
});

require __DIR__.'/auth.php';
