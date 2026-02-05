<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->randomElement([1000000, 2000000, 3000000, 5000000, 10000000]);
        $duration = $this->faker->randomElement([6, 10, 12, 18, 24]);
        $paidMonths = $this->faker->numberBetween(0, $duration - 1);
        $monthlyPrincipal = $amount / $duration;
        $remainingPrincipal = $amount - ($monthlyPrincipal * $paidMonths);

        return [
            'member_id' => Member::factory(),
            'amount' => $amount,
            'interest_rate' => $this->faker->randomElement([1, 1.5, 2, 2.5]),
            'duration' => $duration,
            'remaining_principal' => max(0, $remainingPrincipal),
            'status' => $remainingPrincipal > 0 ? 'active' : 'paid',
        ];
    }

    /**
     * Indicate that the loan is active.
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            $amount = $attributes['amount'] ?? 5000000;
            $duration = $attributes['duration'] ?? 12;
            $paidMonths = $this->faker->numberBetween(0, $duration - 1);
            $monthlyPrincipal = $amount / $duration;
            $remainingPrincipal = $amount - ($monthlyPrincipal * $paidMonths);

            return [
                'remaining_principal' => max(1, $remainingPrincipal),
                'status' => 'active',
            ];
        });
    }

    /**
     * Indicate that the loan is fully paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'remaining_principal' => 0,
            'status' => 'paid',
        ]);
    }

    /**
     * Indicate that the loan is new (no payments yet).
     */
    public function fresh(): static
    {
        return $this->state(fn (array $attributes) => [
            'remaining_principal' => $attributes['amount'] ?? 5000000,
            'status' => 'active',
        ]);
    }
}
