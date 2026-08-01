<?php

namespace Database\Factories;

use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
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
            'name' => fake()->randomElement(['General Admission', 'VIP', 'Early Bird', 'Student', 'Group Pass']),
            'description' => fake()->sentence(),
            'quantity' => fake()->optional(0.8)->numberBetween(50, 200),
            'quantity_sold' => 0,
            'order' => 0,
            'price' => 0.00, // Free events only
            'currency' => 'USD',
            'sales_start_at' => fake()->optional(0.3)->dateTimeBetween('-1 month', 'now'),
            'sales_end_at' => fake()->optional(0.3)->dateTimeBetween('now', '+1 month'),
            'is_visible' => true,
            'min_per_order' => 1,
            'max_per_order' => fake()->randomElement([1, 2, 5, 10]),
        ];
    }
}
