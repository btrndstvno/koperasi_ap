<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement([
            Transaction::TYPE_SAVING_DEPOSIT,
            Transaction::TYPE_LOAN_REPAYMENT,
        ]);

        $amountSaving = $type === Transaction::TYPE_SAVING_DEPOSIT 
            ? $this->faker->randomElement([10000, 20000, 50000, 100000]) 
            : 0;
        
        $amountPrincipal = $type === Transaction::TYPE_LOAN_REPAYMENT 
            ? $this->faker->randomFloat(2, 100000, 500000) 
            : 0;
        
        $amountInterest = $type === Transaction::TYPE_LOAN_REPAYMENT 
            ? $this->faker->randomFloat(2, 10000, 50000) 
            : 0;

        return [
            'member_id' => Member::factory(),
            'loan_id' => $type === Transaction::TYPE_LOAN_REPAYMENT ? Loan::factory() : null,
            'transaction_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'type' => $type,
            'amount_saving' => $amountSaving,
            'amount_principal' => $amountPrincipal,
            'amount_interest' => $amountInterest,
            'total_amount' => $amountSaving + $amountPrincipal + $amountInterest,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that this is a saving deposit transaction.
     */
    public function savingDeposit(float $amount = 10000): static
    {
        return $this->state(fn (array $attributes) => [
            'loan_id' => null,
            'type' => Transaction::TYPE_SAVING_DEPOSIT,
            'amount_saving' => $amount,
            'amount_principal' => 0,
            'amount_interest' => 0,
            'total_amount' => $amount,
        ]);
    }

    /**
     * Indicate that this is a loan repayment transaction.
     */
    public function loanRepayment(float $principal = 100000, float $interest = 10000): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Transaction::TYPE_LOAN_REPAYMENT,
            'amount_saving' => 0,
            'amount_principal' => $principal,
            'amount_interest' => $interest,
            'total_amount' => $principal + $interest,
        ]);
    }

    /**
     * Indicate that this is a saving withdrawal transaction.
     */
    public function savingWithdraw(float $amount = 100000): static
    {
        return $this->state(fn (array $attributes) => [
            'loan_id' => null,
            'type' => Transaction::TYPE_SAVING_WITHDRAW,
            'amount_saving' => $amount,
            'amount_principal' => 0,
            'amount_interest' => 0,
            'total_amount' => $amount,
        ]);
    }
}
