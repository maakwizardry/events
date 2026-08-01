<?php

namespace Database\Factories;

use App\Models\EventInvite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventInvite>
 */
class EventInviteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => \App\Models\Event::factory(),
            'invited_by' => \App\Models\User::factory(),
            'email' => fake()->email(),
            'status' => fake()->randomElement(['pending', 'accepted', 'declined']),
            'accepted_at' => fake()->optional(0.3)->dateTimeBetween('-1 week', 'now'),
            'expires_at' => fake()->optional(0.5)->dateTimeBetween('now', '+1 month'),
        ];
    }

    public function pending()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'accepted_at' => null,
        ]);
    }

    public function accepted()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'accepted_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }
}
