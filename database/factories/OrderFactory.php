<?php

namespace Database\Factories;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'user_id' => User::factory(),
            'total_amount' => fake()->numberBetween(50_000, 2_000_000),
            'payment_status' => PaymentStatus::Unpaid,
            'fulfillment_status' => FulfillmentStatus::Pending,
            'tracking_number' => null,
            'payment_reference' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Processing,
            'payment_reference' => 'PAY-'.fake()->unique()->numerify('##########'),
            'paid_at' => now(),
        ]);
    }

    public function shipped(): static
    {
        return $this->paid()->state(fn (array $attributes) => [
            'fulfillment_status' => FulfillmentStatus::Shipped,
            'tracking_number' => 'JNE'.fake()->numerify('############'),
        ]);
    }
}
