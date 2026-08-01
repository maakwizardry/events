<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_guest_can_register_for_public_event()
    {
        $event = Event::factory()->create([
            'visibility' => 'public',
            'status' => 'published',
            'capacity' => 100,
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'quantity' => 50,
        ]);

        $response = $this->postJson("/api/v1/public/events/{$event->uuid}/register", [
            'ticket_type_id' => $ticketType->id,
            'guest_name' => 'Jane Doe',
            'guest_email' => 'jane@example.com',
            'guest_phone' => '+1234567890',
            'quantity' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['uuid', 'status', 'qr_code_data', 'qr_code_url'],
            ])
            ->assertJson([
                'data' => [
                    'status' => 'confirmed',
                    'quantity' => 1,
                ],
            ]);

        $this->assertDatabaseHas('registrations', [
            'event_id' => $event->id,
            'guest_email' => 'jane@example.com',
            'status' => 'confirmed',
        ]);
    }

    public function test_authenticated_user_can_register_for_event()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'visibility' => 'public',
            'status' => 'published',
            'capacity' => 100,
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'quantity' => 50,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/events/{$event->uuid}/register", [
                'ticket_type_id' => $ticketType->id,
                'quantity' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['uuid', 'status', 'qr_code_data'],
            ])
            ->assertJson([
                'data' => [
                    'status' => 'confirmed',
                    'quantity' => 2,
                ],
            ]);

        $this->assertDatabaseHas('registrations', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'quantity' => 2,
            'status' => 'confirmed',
        ]);
    }

    public function test_registration_added_to_waitlist_when_event_full()
    {
        $event = Event::factory()->create([
            'visibility' => 'public',
            'status' => 'published',
            'capacity' => 10,
            'total_registered' => 10,
            'enable_waitlist' => true,
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'quantity' => 50,
        ]);

        $response = $this->postJson("/api/v1/public/events/{$event->uuid}/register", [
            'ticket_type_id' => $ticketType->id,
            'guest_name' => 'Jane Doe',
            'guest_email' => 'jane@example.com',
            'quantity' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'status' => 'waitlisted',
                ],
            ]);

        $this->assertDatabaseHas('registrations', [
            'event_id' => $event->id,
            'status' => 'waitlisted',
        ]);
    }

    public function test_registration_fails_when_event_full_and_waitlist_disabled()
    {
        $event = Event::factory()->create([
            'visibility' => 'public',
            'status' => 'published',
            'capacity' => 10,
            'total_registered' => 10,
            'enable_waitlist' => false,
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'quantity' => 50,
        ]);

        $response = $this->postJson("/api/v1/public/events/{$event->uuid}/register", [
            'ticket_type_id' => $ticketType->id,
            'guest_name' => 'Jane Doe',
            'guest_email' => 'jane@example.com',
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
    }

    public function test_registration_added_to_waitlist_when_ticket_type_full()
    {
        $event = Event::factory()->create([
            'visibility' => 'public',
            'status' => 'published',
            'capacity' => 100,
            'enable_waitlist' => true,
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'quantity' => 10,
            'quantity_sold' => 10,
        ]);

        $response = $this->postJson("/api/v1/public/events/{$event->uuid}/register", [
            'ticket_type_id' => $ticketType->id,
            'guest_name' => 'Jane Doe',
            'guest_email' => 'jane@example.com',
            'quantity' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'status' => 'waitlisted',
                ],
            ]);
    }

    public function test_user_can_view_their_registrations()
    {
        $user = User::factory()->create();
        $event1 = Event::factory()->create();
        $event2 = Event::factory()->create();

        Registration::factory()->create([
            'event_id' => $event1->id,
            'user_id' => $user->id,
        ]);

        Registration::factory()->create([
            'event_id' => $event2->id,
            'user_id' => $user->id,
        ]);

        // Registration from another user
        Registration::factory()->create([
            'event_id' => $event1->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/registrations');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_view_single_registration()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'confirmed',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson("/api/v1/registrations/{$registration->uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'uuid' => $registration->uuid,
                    'status' => 'confirmed',
                ],
            ]);
    }

    public function test_guest_can_view_registration_with_uuid()
    {
        $event = Event::factory()->create();
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'guest_email' => 'guest@example.com',
        ]);

        $response = $this->getJson("/api/v1/public/registrations/{$registration->uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'uuid' => $registration->uuid,
                ],
            ]);
    }

    public function test_user_can_cancel_their_registration()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['total_registered' => 1]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'confirmed',
            'quantity' => 1,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/registrations/{$registration->uuid}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Registration cancelled successfully',
            ]);

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'status' => 'cancelled',
        ]);

        $this->assertEquals(0, $event->fresh()->total_registered);
    }

    public function test_user_cannot_cancel_other_users_registration()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $event = Event::factory()->create();
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user1->id,
        ]);

        $token = $user2->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/registrations/{$registration->uuid}/cancel");

        $response->assertStatus(403);
    }

    public function test_qr_code_is_generated_for_registration()
    {
        $event = Event::factory()->create([
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
        ]);

        $response = $this->postJson("/api/v1/public/events/{$event->uuid}/register", [
            'ticket_type_id' => $ticketType->id,
            'guest_name' => 'Jane Doe',
            'guest_email' => 'jane@example.com',
            'quantity' => 1,
        ]);

        $response->assertStatus(201);

        $registration = Registration::where('guest_email', 'jane@example.com')->first();

        $this->assertNotNull($registration->qr_code_data);
        $this->assertEquals(32, strlen($registration->qr_code_data));
    }

    public function test_registration_fails_with_invalid_ticket_type()
    {
        $event = Event::factory()->create([
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $response = $this->postJson("/api/v1/public/events/{$event->uuid}/register", [
            'ticket_type_id' => 99999,
            'guest_name' => 'Jane Doe',
            'guest_email' => 'jane@example.com',
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
    }

    public function test_guest_registration_requires_guest_details()
    {
        $event = Event::factory()->create([
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
        ]);

        $response = $this->postJson("/api/v1/public/events/{$event->uuid}/register", [
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['guest_name', 'guest_email']);
    }
}
