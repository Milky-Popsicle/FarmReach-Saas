<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.fake()->unique()->numerify('########'),
            'buyer_id' => User::factory()->buyer(),
            'farmer_id' => User::factory()->farmer(),
            'order_type' => 'standard',
            'total_amount' => 100.00,
            'delivery_method' => 'deliver',
            'delivery_address' => fake()->address(),
            'shipping_fee' => 0.00,
            'status' => 'pending',
            'notes' => null,
        ];
    }
}
