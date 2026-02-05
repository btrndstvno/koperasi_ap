<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departments = ['PRODUKSI', 'HRD', 'FINANCE', 'MARKETING', 'IT', 'WAREHOUSE', 'QC', 'MAINTENANCE', 'PURCHASING'];
        
        return [
            'nik' => $this->faker->unique()->numerify('EMP####'),
            'name' => $this->faker->name(),
            'dept' => $this->faker->randomElement($departments),
            'employee_status' => $this->faker->randomElement(['monthly', 'weekly']),
            'savings_balance' => $this->faker->randomFloat(2, 0, 5000000),
        ];
    }

    /**
     * Indicate that the member is monthly employee.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_status' => 'monthly',
        ]);
    }

    /**
     * Indicate that the member is weekly employee.
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_status' => 'weekly',
        ]);
    }

    /**
     * Indicate that the member has zero savings.
     */
    public function withZeroSavings(): static
    {
        return $this->state(fn (array $attributes) => [
            'savings_balance' => 0,
        ]);
    }
}
