<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\MemberAddedNotification;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_existing_user_as_member_sends_notification()
    {
        Notification::fake();

        $admin = User::factory()->create();
        $existingUser = User::factory()->create(['email' => 'member@example.com']);
        $org = Organization::factory()->create();

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/organizations/{$org->uuid}/members", [
                'email' => 'member@example.com',
                'role' => 'member',
            ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Member added successfully']);

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $org->id,
            'user_id' => $existingUser->id,
            'role' => 'member',
        ]);

        Notification::assertSentTo($existingUser, MemberAddedNotification::class);
    }

    public function test_inviting_non_existing_user_creates_invitation()
    {
        Notification::fake();

        $admin = User::factory()->create();
        $org = Organization::factory()->create();

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/organizations/{$org->uuid}/members", [
                'email' => 'newuser@example.com',
                'role' => 'member',
            ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Invitation sent successfully'])
            ->assertJsonStructure([
                'invitation' => ['email', 'role', 'expires_at', 'token'],
            ]);

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $org->id,
            'email' => 'newuser@example.com',
            'role' => 'member',
            'status' => 'pending',
        ]);

        Notification::assertSentOnDemand(OrganizationInvitationNotification::class);
    }

    public function test_cannot_send_duplicate_pending_invitation()
    {
        $admin = User::factory()->create();
        $org = Organization::factory()->create();

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        // Create existing pending invitation
        OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => 'newuser@example.com',
            'role' => 'member',
            'invited_by' => $admin->id,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/organizations/{$org->uuid}/members", [
                'email' => 'newuser@example.com',
                'role' => 'admin',
            ]);

        $response->assertStatus(422)
            ->assertSee('already been sent');
    }

    public function test_can_view_invitation_details()
    {
        $admin = User::factory()->create(['name' => 'Admin User']);
        $org = Organization::factory()->create(['name' => 'Test Org']);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => 'newuser@example.com',
            'role' => 'member',
            'invited_by' => $admin->id,
        ]);

        $response = $this->getJson("/api/v1/invitations/{$invitation->token}");

        $response->assertStatus(200)
            ->assertJson([
                'invitation' => [
                    'organization_name' => 'Test Org',
                    'role' => 'member',
                    'invited_by' => 'Admin User',
                    'email' => 'newuser@example.com',
                ],
            ]);
    }

    public function test_authenticated_user_can_accept_invitation()
    {
        $admin = User::factory()->create();
        $newUser = User::factory()->create(['email' => 'newuser@example.com']);
        $org = Organization::factory()->create();

        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => 'newuser@example.com',
            'role' => 'member',
            'invited_by' => $admin->id,
        ]);

        $token = $newUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/invitations/{$invitation->token}/accept");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Invitation accepted successfully']);

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $org->id,
            'user_id' => $newUser->id,
            'role' => 'member',
        ]);

        $this->assertDatabaseHas('organization_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);
    }

    public function test_cannot_accept_invitation_without_authentication()
    {
        $admin = User::factory()->create();
        $org = Organization::factory()->create();

        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => 'newuser@example.com',
            'role' => 'member',
            'invited_by' => $admin->id,
        ]);

        $response = $this->postJson("/api/v1/invitations/{$invitation->token}/accept");

        $response->assertStatus(401);
    }

    public function test_cannot_accept_invitation_with_different_email()
    {
        $admin = User::factory()->create();
        $wrongUser = User::factory()->create(['email' => 'wrong@example.com']);
        $org = Organization::factory()->create();

        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => 'newuser@example.com',
            'role' => 'member',
            'invited_by' => $admin->id,
        ]);

        $token = $wrongUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/invitations/{$invitation->token}/accept");

        $response->assertStatus(403)
            ->assertSee('newuser@example.com');
    }

    public function test_cannot_accept_expired_invitation()
    {
        $admin = User::factory()->create();
        $newUser = User::factory()->create(['email' => 'newuser@example.com']);
        $org = Organization::factory()->create();

        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => 'newuser@example.com',
            'role' => 'member',
            'invited_by' => $admin->id,
            'expires_at' => now()->subDay(),
        ]);

        $token = $newUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/invitations/{$invitation->token}/accept");

        $response->assertStatus(422)
            ->assertSee('expired');

        $this->assertDatabaseHas('organization_invitations', [
            'id' => $invitation->id,
            'status' => 'expired',
        ]);
    }

    public function test_user_can_list_their_pending_invitations()
    {
        $admin = User::factory()->create();
        $newUser = User::factory()->create(['email' => 'newuser@example.com']);
        $org1 = Organization::factory()->create(['name' => 'Org 1']);
        $org2 = Organization::factory()->create(['name' => 'Org 2']);

        // Create pending invitations for the user
        OrganizationInvitation::create([
            'organization_id' => $org1->id,
            'email' => 'newuser@example.com',
            'role' => 'member',
            'invited_by' => $admin->id,
        ]);

        OrganizationInvitation::create([
            'organization_id' => $org2->id,
            'email' => 'newuser@example.com',
            'role' => 'admin',
            'invited_by' => $admin->id,
        ]);

        // Create an invitation for a different email
        OrganizationInvitation::create([
            'organization_id' => $org1->id,
            'email' => 'other@example.com',
            'role' => 'member',
            'invited_by' => $admin->id,
        ]);

        $token = $newUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/invitations/my');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'invitations')
            ->assertJsonFragment(['organization_name' => 'Org 1'])
            ->assertJsonFragment(['organization_name' => 'Org 2']);
    }

    public function test_cannot_accept_already_accepted_invitation()
    {
        $admin = User::factory()->create();
        $newUser = User::factory()->create(['email' => 'newuser@example.com']);
        $org = Organization::factory()->create();

        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => 'newuser@example.com',
            'role' => 'member',
            'invited_by' => $admin->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $token = $newUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/invitations/{$invitation->token}/accept");

        $response->assertStatus(422)
            ->assertSee('already been accepted');
    }

    public function test_invitation_cannot_be_accepted_if_user_already_member()
    {
        $admin = User::factory()->create();
        $user = User::factory()->create(['email' => 'member@example.com']);
        $org = Organization::factory()->create();

        // User is already a member
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => 'member@example.com',
            'role' => 'admin',
            'invited_by' => $admin->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/invitations/{$invitation->token}/accept");

        $response->assertStatus(422)
            ->assertSee('already a member');
    }
}
