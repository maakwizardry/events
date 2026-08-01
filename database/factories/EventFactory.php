<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->sentence(3);
        $startsAt = fake()->dateTimeBetween('now', '+3 months');
        $endsAt = (clone $startsAt)->modify('+' . fake()->numberBetween(1, 8) . ' hours');

        return [
            'organization_id' => \App\Models\Organization::factory(),
            'name' => rtrim($name, '.'),
            'slug' => \Illuminate\Support\Str::slug($name),
            'description' => fake()->paragraphs(3, true),
            'cover_image_url' => fake()->imageUrl(1200, 600, 'event'),
            'visibility' => fake()->randomElement(['public', 'private']),
            'location_type' => fake()->randomElement(['physical', 'online', 'hybrid']),
            'location_address' => fake()->streetAddress(),
            'location_city' => fake()->city(),
            'location_state' => fake()->state(),
            'location_country' => fake()->country(),
            'location_zip' => fake()->postcode(),
            'location_latitude' => fake()->latitude(),
            'location_longitude' => fake()->longitude(),
            'online_meeting_url' => fake()->url(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => fake()->timezone(),
            'capacity' => fake()->optional(0.7)->numberBetween(50, 500),
            'total_registered' => 0,
            'enable_waitlist' => fake()->boolean(70),
            'auto_approve_registrations' => true,
            'require_approval' => false,
            'registration_opens_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'registration_closes_at' => $startsAt,
            'category' => fake()->randomElement(['conference', 'workshop', 'meetup', 'webinar', 'networking']),
            'tags' => fake()->randomElements(['tech', 'business', 'startup', 'ai', 'design', 'marketing'], fake()->numberBetween(2, 4)),
            'status' => fake()->randomElement(['draft', 'published', 'cancelled', 'completed']),
        ];
    }

    public function public()
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'public',
            'status' => 'published',
        ]);
    }

    public function private()
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'private',
        ]);
    }

    public function upcoming()
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+3 months');

        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+' . fake()->numberBetween(1, 8) . ' hours'),
            'status' => 'published',
        ]);
    }
}
