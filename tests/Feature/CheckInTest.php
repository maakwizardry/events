<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_check_in_attendee()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/events/{$event->uuid}/check-in", [
                'qr_code_data' => $registration->qr_code_data,
                'location' => 'Main Entrance',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Check-in successful',
            ]);

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'is_checked_in' => true,
            'checked_in_by' => $user->id,
            'check_in_location' => 'Main Entrance',
        ]);

        $this->assertNotNull($registration->fresh()->checked_in_at);
    }

    public function test_non_organizer_cannot_check_in_attendee()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/events/{$event->uuid}/check-in", [
                'qr_code_data' => $registration->qr_code_data,
            ]);

        $response->assertStatus(403);
    }

    public function test_check_in_fails_with_invalid_qr_code()
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
            ->postJson("/api/v1/events/{$event->uuid}/check-in", [
                'qr_code_data' => 'invalid-qr-code',
            ]);

        $response->assertStatus(404);
    }

    public function test_check_in_fails_for_waitlisted_registration()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => 'waitlisted',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/events/{$event->uuid}/check-in", [
                'qr_code_data' => $registration->qr_code_data,
            ]);

        $response->assertStatus(422)
            ->assertSee('confirmed registrations');
    }

    public function test_check_in_fails_for_cancelled_registration()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => 'cancelled',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/events/{$event->uuid}/check-in", [
                'qr_code_data' => $registration->qr_code_data,
            ]);

        $response->assertStatus(422)
            ->assertSee('confirmed registrations');
    }

    public function test_duplicate_check_in_fails()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
            'is_checked_in' => true,
            'checked_in_at' => now(),
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/events/{$event->uuid}/check-in", [
                'qr_code_data' => $registration->qr_code_data,
            ]);

        $response->assertStatus(422)
            ->assertSee('already been checked in');
    }

    public function test_organizer_can_list_event_registrations()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);

        Registration::factory()->count(3)->create([
            'event_id' => $event->id,
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson("/api/v1/events/{$event->uuid}/registrations");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_organizer_can_filter_registrations_by_status()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);

        Registration::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
        ]);

        Registration::factory()->create([
            'event_id' => $event->id,
            'status' => 'waitlisted',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson("/api/v1/events/{$event->uuid}/registrations?status=confirmed");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_organizer_can_filter_registrations_by_check_in_status()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);

        Registration::factory()->create([
            'event_id' => $event->id,
            'is_checked_in' => true,
        ]);

        Registration::factory()->create([
            'event_id' => $event->id,
            'is_checked_in' => false,
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson("/api/v1/events/{$event->uuid}/registrations?checked_in=true");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_non_organizer_cannot_view_registrations()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson("/api/v1/events/{$event->uuid}/registrations");

        $response->assertStatus(403);
    }

    public function test_organizer_can_export_registrations_as_csv()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Test Event',
            'slug' => 'test-event',
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'user_id' => null, // Ensure it's a guest registration
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get("/api/v1/events/{$event->uuid}/registrations/export");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // Get the streamed content
        $content = $response->streamedContent();

        $this->assertStringContainsString('Registration ID', $content);
        $this->assertStringContainsString('Attendee Name', $content);
        $this->assertStringContainsString('John Doe', $content);
        $this->assertStringContainsString('john@example.com', $content);
        $this->assertStringContainsString($registration->uuid, $content);
    }

    public function test_non_organizer_cannot_export_csv()
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get("/api/v1/events/{$event->uuid}/registrations/export");

        $response->assertStatus(403);
    }
}
