<?php

use App\Core\Tenancy\Support\TenantConnectionResolver;
use App\Core\Users\Models\Invitation;

test('an admin cannot revoke a pending invitation belonging to another studio (separate physical databases)', function () {
    $studioB = provisionRealStudioWithRole('admin');
    $invitationB = Invitation::factory()->create();
    app(TenantConnectionResolver::class)->release();

    $studioA = provisionRealStudioWithRole('admin');

    $this->actingAs($studioA['user'])->delete("/users/invitations/{$invitationB->id}")
        ->assertNotFound();

    app(TenantConnectionResolver::class)->release();
    unlink($studioA['path']);
    unlink($studioB['path']);
});
