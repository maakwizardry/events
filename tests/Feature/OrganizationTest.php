<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_organization()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/organizations', [
                'name' => 'Tech Meetups',
                'slug' => 'tech-meetups',
                'description' => 'A community for tech enthusiasts',
                'primary_color' => '#3B82F6',
                'secondary_color' => '#1E40AF',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['uuid', 'name', 'slug', 'description'],
            ])
            ->assertJson([
                'data' => [
                    'name' => 'Tech Meetups',
                    'slug' => 'tech-meetups',
                ],
            ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Tech Meetups',
            'slug' => 'tech-meetups',
        ]);

        // Creator should be owner
        $org = Organization::where('slug', 'tech-meetups')->first();
        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_unauthenticated_user_cannot_create_organization()
    {
        $response = $this->postJson('/api/v1/organizations', [
            'name' => 'Test Org',
            'slug' => 'test-org',
        ]);

        $response->assertStatus(401);
    }

    public function test_organization_creation_fails_with_duplicate_slug()
    {
        Organization::factory()->create(['slug' => 'tech-meetups']);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/organizations', [
                'name' => 'Tech Meetups',
                'slug' => 'tech-meetups',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_user_can_list_their_organizations()
    {
        $user = User::factory()->create();
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        $org3 = Organization::factory()->create(); // Not member of this one

        OrganizationMember::create([
            'organization_id' => $org1->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        OrganizationMember::create([
            'organization_id' => $org2->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/organizations');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => $org1->name])
            ->assertJsonFragment(['name' => $org2->name])
            ->assertJsonMissing(['name' => $org3->name]);
    }

    public function test_user_can_view_organization_details()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create([
            'name' => 'Tech Meetups',
            'slug' => 'tech-meetups',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson("/api/v1/organizations/{$org->uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'uuid' => $org->uuid,
                    'name' => 'Tech Meetups',
                    'slug' => 'tech-meetups',
                ],
            ]);
    }

    public function test_user_cannot_view_organization_they_are_not_member_of()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson("/api/v1/organizations/{$org->uuid}");

        $response->assertStatus(403);
    }

    public function test_owner_can_update_organization()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['name' => 'Old Name']);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/v1/organizations/{$org->uuid}", [
                'name' => 'New Name',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'New Name',
                    'description' => 'Updated description',
                ],
            ]);

        $this->assertDatabaseHas('organizations', [
            'id' => $org->id,
            'name' => 'New Name',
        ]);
    }

    public function test_member_cannot_update_organization()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['name' => 'Old Name']);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/v1/organizations/{$org->uuid}", [
                'name' => 'New Name',
            ]);

        $response->assertStatus(403);
    }

    public function test_owner_can_delete_organization()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/v1/organizations/{$org->uuid}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('organizations', [
            'id' => $org->id,
        ]);
    }

    public function test_non_owner_cannot_delete_organization()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/v1/organizations/{$org->uuid}");

        $response->assertStatus(403);
    }

    public function test_admin_can_add_member_to_organization()
    {
        $user = User::factory()->create();
        $newMember = User::factory()->create(['email' => 'member@example.com']);
        $org = Organization::factory()->create();

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/organizations/{$org->uuid}/members", [
                'email' => 'member@example.com',
                'role' => 'member',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $org->id,
            'user_id' => $newMember->id,
            'role' => 'member',
        ]);
    }

    public function test_member_cannot_add_members()
    {
        $user = User::factory()->create();
        $newMember = User::factory()->create(['email' => 'newmember@example.com']);
        $org = Organization::factory()->create();

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/organizations/{$org->uuid}/members", [
                'email' => 'newmember@example.com',
                'role' => 'member',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_list_organization_members()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $member1->id,
            'role' => 'member',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $member2->id,
            'role' => 'member',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson("/api/v1/organizations/{$org->uuid}/members");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data'); // 3 members total including admin
    }

    public function test_admin_can_update_member_role()
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $org = Organization::factory()->create();

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $memberRecord = OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/v1/organizations/{$org->uuid}/members/{$memberRecord->id}", [
                'role' => 'admin',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('organization_members', [
            'id' => $memberRecord->id,
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_remove_member()
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $org = Organization::factory()->create();

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $memberRecord = OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/v1/organizations/{$org->uuid}/members/{$memberRecord->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('organization_members', [
            'id' => $memberRecord->id,
        ]);
    }
}
