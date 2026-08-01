<?php

namespace Database\Factories;

use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isGuest = fake()->boolean(30);

        return [
            'event_id' => \App\Models\Event::factory(),
            'ticket_type_id' => \App\Models\TicketType::factory(),
            'user_id' => $isGuest ? null : \App\Models\User::factory(),
            'guest_email' => $isGuest ? fake()->email() : null,
            'guest_name' => $isGuest ? fake()->name() : null,
            'guest_phone' => $isGuest ? fake()->phoneNumber() : null,
            'quantity' => fake()->numberBetween(1, 3),
            'status' => fake()->randomElement(['confirmed', 'waitlisted', 'cancelled', 'pending']),
            'is_checked_in' => false,
            'custom_fields' => fake()->optional(0.3)->passthrough(['dietary' => fake()->randomElement(['vegetarian', 'vegan', 'none'])]),
        ];
    }

    public function confirmed()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    public function checkedIn()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'is_checked_in' => true,
            'checked_in_at' => fake()->dateTimeBetween('-1 day', 'now'),
            'checked_in_by' => \App\Models\User::factory(),
        ]);
    }
}
