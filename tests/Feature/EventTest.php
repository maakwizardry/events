<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_public_events()
    {
        Event::factory()->create([
            'visibility' => 'public',
            'status' => 'published',
            'starts_at' => now()->addDays(1),
        ]);

        Event::factory()->create([
            'visibility' => 'private',
            'status' => 'published',
        ]);

        Event::factory()->create([
            'visibility' => 'public',
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/v1/public/events');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data'); // Only public and published
    }

    public function test_guest_can_view_public_event()
    {
        $event = Event::factory()->create([
            'name' => 'Laravel Workshop',
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $response = $this->getJson("/api/v1/public/events/{$event->uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'uuid' => $event->uuid,
                    'name' => 'Laravel Workshop',
                ],
            ]);
    }

    public function test_guest_cannot_view_private_event()
    {
        $event = Event::factory()->create([
            'visibility' => 'private',
            'status' => 'published',
        ]);

        $response = $this->getJson("/api/v1/public/events/{$event->uuid}");

        $response->assertStatus(404);
    }

    public function test_public_events_can_be_filtered_by_category()
    {
        Event::factory()->create([
            'category' => 'workshop',
            'visibility' => 'public',
            'status' => 'published',
        ]);

        Event::factory()->create([
            'category' => 'conference',
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $response = $this->getJson('/api/v1/public/events?category=workshop');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_public_events_can_be_searched()
    {
        Event::factory()->create([
            'name' => 'Laravel Workshop',
            'visibility' => 'public',
            'status' => 'published',
        ]);

        Event::factory()->create([
            'name' => 'React Conference',
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $response = $this->getJson('/api/v1/public/events?search=Laravel');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Laravel Workshop']);
    }

    public function test_organizer_can_create_event()
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
            ->postJson('/api/v1/events', [
                'organization_id' => $org->id,
                'name' => 'Laravel Workshop 2026',
                'description' => 'Learn Laravel from scratch',
                'visibility' => 'public',
                'location_type' => 'hybrid',
                'location_city' => 'San Francisco',
                'location_country' => 'USA',
                'starts_at' => '2026-09-01 10:00:00',
                'ends_at' => '2026-09-01 16:00:00',
                'timezone' => 'America/Los_Angeles',
                'capacity' => 50,
                'category' => 'workshop',
                'status' => 'published',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['uuid', 'name', 'description'],
            ])
            ->assertJson([
                'data' => [
                    'name' => 'Laravel Workshop 2026',
                    'capacity' => 50,
                ],
            ]);

        $this->assertDatabaseHas('events', [
            'name' => 'Laravel Workshop 2026',
            'organization_id' => $org->id,
        ]);
    }

    public function test_non_member_cannot_create_event_for_organization()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/events', [
                'organization_id' => $org->id,
                'name' => 'Test Event',
                'visibility' => 'public',
                'location_type' => 'online',
                'starts_at' => now()->addDays(1)->toDateTimeString(),
                'ends_at' => now()->addDays(1)->addHours(2)->toDateTimeString(),
                'timezone' => 'UTC',
            ]);

        $response->assertStatus(403);
    }

    public function test_organizer_can_update_event()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Old Name',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/v1/events/{$event->uuid}", [
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

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'name' => 'New Name',
        ]);
    }

    public function test_non_organizer_cannot_update_event()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/v1/events/{$event->uuid}", [
                'name' => 'New Name',
            ]);

        $response->assertStatus(403);
    }

    public function test_organizer_can_delete_event()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/v1/events/{$event->uuid}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('events', [
            'id' => $event->id,
        ]);
    }

    public function test_organizer_can_list_organization_events()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event1 = Event::factory()->create(['organization_id' => $org->id]);
        $event2 = Event::factory()->create(['organization_id' => $org->id]);
        $event3 = Event::factory()->create(); // Different org

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson("/api/v1/organizations/{$org->uuid}/events");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['uuid' => $event1->uuid])
            ->assertJsonFragment(['uuid' => $event2->uuid])
            ->assertJsonMissing(['uuid' => $event3->uuid]);
    }

    public function test_organizer_can_create_ticket_type()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/events/{$event->uuid}/ticket-types", [
                'name' => 'General Admission',
                'description' => 'Standard entry ticket',
                'price' => 0,
                'quantity' => 100,
                'sales_start_at' => now()->toDateTimeString(),
                'sales_end_at' => now()->addDays(30)->toDateTimeString(),
                'order' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'General Admission',
                'quantity' => 100,
            ]);

        $this->assertDatabaseHas('ticket_types', [
            'event_id' => $event->id,
            'name' => 'General Admission',
        ]);
    }

    public function test_guest_can_list_ticket_types_for_public_event()
    {
        $event = Event::factory()->create([
            'visibility' => 'public',
            'status' => 'published',
        ]);

        TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'Early Bird',
        ]);

        TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'Regular',
        ]);

        $response = $this->getJson("/api/v1/public/events/{$event->uuid}/ticket-types");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Early Bird'])
            ->assertJsonFragment(['name' => 'Regular']);
    }

    public function test_organizer_can_update_ticket_type()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'Old Name',
            'quantity' => 50,
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/v1/events/{$event->uuid}/ticket-types/{$ticketType->uuid}", [
                'name' => 'New Name',
                'quantity' => 100,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'New Name',
                'quantity' => 100,
            ]);

        $this->assertDatabaseHas('ticket_types', [
            'id' => $ticketType->id,
            'name' => 'New Name',
            'quantity' => 100,
        ]);
    }

    public function test_organizer_can_delete_ticket_type()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/v1/events/{$event->uuid}/ticket-types/{$ticketType->uuid}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('ticket_types', [
            'id' => $ticketType->id,
        ]);
    }

    public function test_guest_can_check_event_availability()
    {
        $event = Event::factory()->create([
            'visibility' => 'public',
            'status' => 'published',
            'capacity' => 100,
            'total_registered' => 75,
        ]);

        $response = $this->getJson("/api/v1/public/events/{$event->uuid}/availability");

        $response->assertStatus(200)
            ->assertJson([
                'capacity' => 100,
                'total_registered' => 75,
                'available_spots' => 25,
                'is_full' => false,
            ]);
    }

    public function test_event_availability_shows_full_when_capacity_reached()
    {
        $event = Event::factory()->create([
            'visibility' => 'public',
            'status' => 'published',
            'capacity' => 100,
            'total_registered' => 100,
        ]);

        $response = $this->getJson("/api/v1/public/events/{$event->uuid}/availability");

        $response->assertStatus(200)
            ->assertJson([
                'capacity' => 100,
                'total_registered' => 100,
                'available_spots' => 0,
                'is_full' => true,
            ]);
    }
}
