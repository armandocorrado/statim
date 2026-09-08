<?php

use App\Core\Agenda\Http\Controllers\AgendaController;
use App\Core\Agenda\Http\Controllers\AppointmentController;
use App\Core\Agenda\Http\Controllers\PatientSearchController;
use App\Core\Consents\Http\Controllers\ConsentController;
use App\Core\Patients\Http\Controllers\PatientController;
use App\Core\Users\Http\Controllers\InvitationAcceptController;
use App\Core\Users\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
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
