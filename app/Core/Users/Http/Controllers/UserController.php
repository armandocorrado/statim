<?php

namespace App\Core\Users\Http\Controllers;

use App\Core\Audit\Support\AuditRecorder;
use App\Core\Users\Http\Requests\InviteUserRequest;
use App\Core\Users\Http\Requests\UpdateUserRoleRequest;
use App\Core\Users\Models\Invitation;
use App\Core\Users\Notifications\UserInvited;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $invitations = Invitation::query()
            ->with('inviter')
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        $roles = Role::where('tenant_id', $request->user()->tenant_id)
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name');

        return Inertia::render('Users/Index', [
            'users' => $users,
            'invitations' => $invitations,
            'roles' => $roles,
        ]);
    }

    public function storeInvitation(InviteUserRequest $request): RedirectResponse
    {
        $plainTextToken = Str::random(40);

        $invitation = new Invitation($request->validated());
        $invitation->token_hash = hash('sha256', $plainTextToken);
        $invitation->invited_by = $request->user()->id;
        $invitation->expires_at = now()->addDays(7);
        $invitation->save();

        Notification::route('mail', $invitation->email)
            ->notify(new UserInvited($request->user()->tenant, $plainTextToken));

        return to_route('users.index')->with('success', 'Invito inviato.');
    }

    public function destroyInvitation(Request $request, Invitation $invitation): RedirectResponse
    {
        $this->authorize('invite', User::class);

        abort_if($invitation->tenant_id !== $request->user()->tenant_id, 404);

        $invitation->delete();

        return to_route('users.index')->with('success', 'Invito revocato.');
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        $oldRoles = $user->getRoleNames()->all();

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        $user->syncRoles([$request->validated('role')]);

        AuditRecorder::record(
            $user,
            'role_changed',
            ['role' => $oldRoles],
            ['role' => [$request->validated('role')]],
        );

        return to_route('users.index')->with('success', 'Ruolo aggiornato.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        $user->is_active = false;
        $user->save();

        return to_route('users.index')->with('success', 'Utente disattivato.');
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        $user->is_active = true;
        $user->save();

        return to_route('users.index')->with('success', 'Utente riattivato.');
    }
}
