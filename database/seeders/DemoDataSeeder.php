<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder creates comprehensive demo data including:
     * - Demo users with known credentials
     * - Organizations with team members
     * - Public and private events
     * - Ticket types with various pricing
     * - Sample registrations in different states
     */
    public function run(): void
    {
        // Create demo users
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@demo.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $organizerUser = User::firstOrCreate(
            ['email' => 'organizer@demo.com'],
            [
                'name' => 'Event Organizer',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $attendeeUser = User::firstOrCreate(
            ['email' => 'attendee@demo.com'],
            [
                'name' => 'John Attendee',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create organizations
        $techOrg = Organization::factory()->create([
            'name' => 'Tech Community',
            'slug' => 'tech-community',
            'description' => 'A vibrant community of tech enthusiasts organizing meetups and conferences.',
            'primary_color' => '#3B82F6',
            'secondary_color' => '#1E40AF',
        ]);

        $businessOrg = Organization::factory()->create([
            'name' => 'Business Network',
            'slug' => 'business-network',
            'description' => 'Professional networking events for business leaders and entrepreneurs.',
            'primary_color' => '#10B981',
            'secondary_color' => '#059669',
        ]);

        // Add members to organizations
        OrganizationMember::create([
            'organization_id' => $techOrg->id,
            'user_id' => $adminUser->id,
            'role' => 'owner',
            'joined_at' => now()->subMonths(6),
        ]);

        OrganizationMember::create([
            'organization_id' => $techOrg->id,
            'user_id' => $organizerUser->id,
            'role' => 'admin',
            'joined_at' => now()->subMonths(3),
        ]);

        OrganizationMember::create([
            'organization_id' => $businessOrg->id,
            'user_id' => $organizerUser->id,
            'role' => 'owner',
            'joined_at' => now()->subMonths(4),
        ]);

        // Create upcoming public events
        $phpConference = Event::factory()->create([
            'organization_id' => $techOrg->id,
            'name' => 'PHP Conference 2026',
            'slug' => 'php-conference-2026',
            'description' => 'The largest PHP conference in the region. Join us for 3 days of talks, workshops, and networking with the PHP community.',
            'visibility' => 'public',
            'status' => 'published',
            'location_type' => 'physical',
            'location_address' => '123 Tech Street',
            'location_city' => 'San Francisco',
            'location_state' => 'CA',
            'location_country' => 'USA',
            'starts_at' => now()->addMonths(2),
            'ends_at' => now()->addMonths(2)->addDays(3),
            'capacity' => 500,
            'total_registered' => 245,
            'category' => 'Technology',
            'tags' => ['php', 'web-development', 'backend'],
        ]);

        $reactWorkshop = Event::factory()->create([
            'organization_id' => $techOrg->id,
            'name' => 'React Workshop for Beginners',
            'slug' => 'react-workshop-beginners',
            'description' => 'Learn React from scratch in this hands-on workshop. No prior experience required!',
            'visibility' => 'public',
            'status' => 'published',
            'location_type' => 'online',
            'online_meeting_url' => 'https://zoom.us/j/example',
            'starts_at' => now()->addWeeks(2),
            'ends_at' => now()->addWeeks(2)->addHours(4),
            'capacity' => 50,
            'total_registered' => 48,
            'enable_waitlist' => true,
            'category' => 'Technology',
            'tags' => ['react', 'javascript', 'workshop'],
        ]);

        $networkingEvent = Event::factory()->create([
            'organization_id' => $businessOrg->id,
            'name' => 'Monthly Business Networking',
            'slug' => 'monthly-business-networking',
            'description' => 'Connect with fellow business professionals over drinks and conversation.',
            'visibility' => 'public',
            'status' => 'published',
            'location_type' => 'physical',
            'location_address' => '456 Business Ave',
            'location_city' => 'New York',
            'location_state' => 'NY',
            'location_country' => 'USA',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(3),
            'capacity' => 100,
            'total_registered' => 67,
            'category' => 'Business',
            'tags' => ['networking', 'business', 'professional'],
        ]);

        $hybridMeetup = Event::factory()->create([
            'organization_id' => $techOrg->id,
            'name' => 'AI & Machine Learning Meetup',
            'slug' => 'ai-ml-meetup',
            'description' => 'Monthly meetup to discuss AI and machine learning trends. Join in person or online!',
            'visibility' => 'public',
            'status' => 'published',
            'location_type' => 'hybrid',
            'location_address' => '789 Innovation Blvd',
            'location_city' => 'Austin',
            'location_state' => 'TX',
            'location_country' => 'USA',
            'online_meeting_url' => 'https://meet.google.com/example',
            'starts_at' => now()->addWeeks(1),
            'ends_at' => now()->addWeeks(1)->addHours(2),
            'capacity' => 75,
            'total_registered' => 42,
            'category' => 'Technology',
            'tags' => ['ai', 'machine-learning', 'data-science'],
        ]);

        // Create a past event
        $pastEvent = Event::factory()->create([
            'organization_id' => $techOrg->id,
            'name' => 'Laravel Summit 2025',
            'slug' => 'laravel-summit-2025',
            'description' => 'Annual Laravel conference with workshops and talks.',
            'visibility' => 'public',
            'status' => 'completed',
            'location_type' => 'physical',
            'location_city' => 'Los Angeles',
            'location_country' => 'USA',
            'starts_at' => now()->subMonths(3),
            'ends_at' => now()->subMonths(3)->addDays(2),
            'capacity' => 300,
            'total_registered' => 287,
            'category' => 'Technology',
        ]);

        // Create ticket types for PHP Conference
        $earlyBird = TicketType::factory()->create([
            'event_id' => $phpConference->id,
            'name' => 'Early Bird',
            'description' => 'Limited early bird pricing - save 40%!',
            'price' => 299.00,
            'quantity' => 100,
            'quantity_sold' => 100,
            'sales_start_at' => now()->subMonths(2),
            'sales_end_at' => now()->subMonth(),
            'order' => 1,
        ]);

        $regularTicket = TicketType::factory()->create([
            'event_id' => $phpConference->id,
            'name' => 'Regular Pass',
            'description' => 'Full access to all conference sessions and workshops.',
            'price' => 499.00,
            'quantity' => 300,
            'quantity_sold' => 120,
            'sales_start_at' => now()->subMonth(),
            'sales_end_at' => $phpConference->starts_at,
            'order' => 2,
        ]);

        $vipPass = TicketType::factory()->create([
            'event_id' => $phpConference->id,
            'name' => 'VIP Pass',
            'description' => 'Includes special workshops, VIP lounge access, and networking dinner.',
            'price' => 899.00,
            'quantity' => 50,
            'quantity_sold' => 25,
            'sales_start_at' => now()->subMonths(2),
            'sales_end_at' => $phpConference->starts_at,
            'order' => 3,
        ]);

        // Create ticket types for React Workshop
        $workshopTicket = TicketType::factory()->create([
            'event_id' => $reactWorkshop->id,
            'name' => 'Workshop Ticket',
            'description' => 'Includes workshop materials and certificate of completion.',
            'price' => 79.00,
            'quantity' => 50,
            'quantity_sold' => 48,
            'sales_start_at' => now()->subWeeks(3),
            'sales_end_at' => $reactWorkshop->starts_at,
            'order' => 1,
        ]);

        // Create ticket types for Networking Event
        $freeTicket = TicketType::factory()->create([
            'event_id' => $networkingEvent->id,
            'name' => 'Free Admission',
            'description' => 'Free entry to the networking event.',
            'price' => 0.00,
            'quantity' => 100,
            'quantity_sold' => 67,
            'sales_start_at' => now()->subWeeks(2),
            'sales_end_at' => $networkingEvent->starts_at,
            'order' => 1,
        ]);

        // Create ticket types for AI Meetup
        $inPersonTicket = TicketType::factory()->create([
            'event_id' => $hybridMeetup->id,
            'name' => 'In-Person Attendance',
            'description' => 'Attend in person with refreshments included.',
            'price' => 25.00,
            'quantity' => 40,
            'quantity_sold' => 22,
            'sales_start_at' => now()->subWeeks(2),
            'sales_end_at' => $hybridMeetup->starts_at,
            'order' => 1,
        ]);

        $virtualTicket = TicketType::factory()->create([
            'event_id' => $hybridMeetup->id,
            'name' => 'Virtual Attendance',
            'description' => 'Join us online via video conference.',
            'price' => 0.00,
            'quantity' => null, // Unlimited
            'quantity_sold' => 20,
            'sales_start_at' => now()->subWeeks(2),
            'sales_end_at' => $hybridMeetup->starts_at,
            'order' => 2,
        ]);

        // Create sample registrations for PHP Conference
        // Authenticated user registrations
        Registration::factory()->create([
            'event_id' => $phpConference->id,
            'ticket_type_id' => $regularTicket->id,
            'user_id' => $attendeeUser->id,
            'status' => 'confirmed',
            'quantity' => 1,
            'is_checked_in' => false,
        ]);

        // Guest registrations
        Registration::factory()->count(5)->create([
            'event_id' => $phpConference->id,
            'ticket_type_id' => $regularTicket->id,
            'user_id' => null,
            'status' => 'confirmed',
            'quantity' => 1,
            'is_checked_in' => false,
        ]);

        // VIP registrations
        Registration::factory()->count(3)->create([
            'event_id' => $phpConference->id,
            'ticket_type_id' => $vipPass->id,
            'user_id' => null,
            'status' => 'confirmed',
            'quantity' => 1,
            'is_checked_in' => false,
        ]);

        // React Workshop registrations (nearly full)
        Registration::factory()->count(10)->create([
            'event_id' => $reactWorkshop->id,
            'ticket_type_id' => $workshopTicket->id,
            'user_id' => null,
            'status' => 'confirmed',
            'quantity' => 1,
            'is_checked_in' => false,
        ]);

        // Waitlisted registrations
        Registration::factory()->count(3)->create([
            'event_id' => $reactWorkshop->id,
            'ticket_type_id' => $workshopTicket->id,
            'user_id' => null,
            'status' => 'waitlisted',
            'quantity' => 1,
            'is_checked_in' => false,
        ]);

        // Networking event registrations
        Registration::factory()->count(15)->create([
            'event_id' => $networkingEvent->id,
            'ticket_type_id' => $freeTicket->id,
            'user_id' => null,
            'status' => 'confirmed',
            'quantity' => 1,
            'is_checked_in' => false,
        ]);

        // Hybrid meetup registrations
        Registration::factory()->count(8)->create([
            'event_id' => $hybridMeetup->id,
            'ticket_type_id' => $inPersonTicket->id,
            'user_id' => null,
            'status' => 'confirmed',
            'quantity' => 1,
            'is_checked_in' => false,
        ]);

        Registration::factory()->count(12)->create([
            'event_id' => $hybridMeetup->id,
            'ticket_type_id' => $virtualTicket->id,
            'user_id' => null,
            'status' => 'confirmed',
            'quantity' => 1,
            'is_checked_in' => false,
        ]);

        // Past event registrations (with check-ins)
        Registration::factory()->count(20)->create([
            'event_id' => $pastEvent->id,
            'user_id' => null,
            'status' => 'confirmed',
            'quantity' => 1,
            'is_checked_in' => true,
            'checked_in_at' => $pastEvent->starts_at->addHours(rand(0, 2)),
            'checked_in_by' => $adminUser->id,
        ]);

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('');
        $this->command->info('Demo Users:');
        $this->command->info('  - admin@demo.com (password: password)');
        $this->command->info('  - organizer@demo.com (password: password)');
        $this->command->info('  - attendee@demo.com (password: password)');
        $this->command->info('');
        $this->command->info('Organizations:');
        $this->command->info('  - Tech Community (' . $techOrg->uuid . ')');
        $this->command->info('  - Business Network (' . $businessOrg->uuid . ')');
        $this->command->info('');
        $this->command->info('Created 5 events with various configurations:');
        $this->command->info('  - PHP Conference 2026 (upcoming, 500 capacity, 245 registered)');
        $this->command->info('  - React Workshop (upcoming, nearly full, waitlist enabled)');
        $this->command->info('  - Business Networking (upcoming)');
        $this->command->info('  - AI & ML Meetup (hybrid event)');
        $this->command->info('  - Laravel Summit (past event with check-ins)');
    }
}
