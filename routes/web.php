<?php

use App\Core\Agenda\Http\Controllers\AgendaController;
use App\Core\Agenda\Http\Controllers\AppointmentController;
use App\Core\Agenda\Http\Controllers\PatientSearchController;
use App\Core\Billing\Http\Controllers\BillingDocumentController;
use App\Core\Consents\Http\Controllers\ConsentController;
use App\Core\Patients\Http\Controllers\PatientController;
use App\Core\Users\Http\Controllers\InvitationAcceptController;
use App\Core\Users\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Modules\Dental\Http\Controllers\DentalAlertController;
use App\Modules\Dental\Http\Controllers\DentalAnamnesisController;
use App\Modules\Dental\Http\Controllers\DentalClinicalRecordController;
use App\Modules\Dental\Http\Controllers\DentalDiaryEntryController;
use App\Modules\Dental\Http\Controllers\DentalDocumentController;
use App\Modules\Dental\Http\Controllers\DentalOdontogramController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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

    Route::post('patients/{patient}/consents', [ConsentController::class, 'store'])->name('patients.consents.store');
    Route::patch('patients/{patient}/consents/{consent}/revoke', [ConsentController::class, 'revoke'])->name('patients.consents.revoke');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users/invitations', [UserController::class, 'storeInvitation'])->name('users.invitations.store');
    Route::delete('users/invitations/{invitation}', [UserController::class, 'destroyInvitation'])->name('users.invitations.destroy');
    Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role.update');
    Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::patch('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
});

Route::middleware('guest')->group(function () {
    Route::get('invitations/{token}', [InvitationAcceptController::class, 'show'])->name('invitations.accept');
    Route::post('invitations/{token}', [InvitationAcceptController::class, 'store'])->name('invitations.store');
});

require __DIR__.'/auth.php';
