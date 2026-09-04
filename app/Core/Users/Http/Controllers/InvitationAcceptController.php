<?php

namespace App\Core\Users\Http\Controllers;

use App\Core\Audit\Support\AuditRecorder;
use App\Core\Users\Http\Requests\AcceptInvitationRequest;
use App\Core\Users\Models\Invitation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvitationAcceptController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = $this->findValidInvitation($token);

        if (! $invitation) {
            return to_route('login')->with('status', 'Questo invito non è più valido.');
        }

        return Inertia::render('Auth/AcceptInvitation', [
            'email' => $invitation->email,
            'token' => $token,
        ]);
    }

    public function store(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->findValidInvitation($token);

        abort_if(! $invitation, HttpResponse::HTTP_GONE, 'Questo invito non è più valido.');

        $user = DB::transaction(function () use ($invitation, $request) {
            $user = new User([
                'tenant_id' => $invitation->tenant_id,
                'name' => $request->validated('name'),
                'email' => $invitation->email,
                'password' => Hash::make($request->validated('password')),
            ]);
            $user->is_active = true;
            $user->email_verified_at = now();
            $user->save();

            app(PermissionRegistrar::class)->setPermissionsTeamId($invitation->tenant_id);
            $user->assignRole($invitation->role);

            // No authenticated actor here (the invitee is self-activating a
            // guest request) — user_id is correctly null; what matters is
            // that the role grant itself lands in the audit trail.
            AuditRecorder::record($user, 'role_assigned', [], ['role' => [$invitation->role]]);

            $invitation->accepted_at = now();
            $invitation->save();

            return $user;
        });

        Auth::login($user);

        return to_route('dashboard')->with('success', 'Account attivato.');
    }

    private function findValidInvitation(string $token): ?Invitation
    {
        $invitation = Invitation::where('token_hash', hash('sha256', $token))->first();

        if (! $invitation || $invitation->isAccepted() || $invitation->isExpired()) {
            return null;
        }

        return $invitation;
    }
}
