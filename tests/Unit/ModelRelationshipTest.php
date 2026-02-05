<?php

namespace Tests\Unit;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_has_many_loans(): void
    {
        $member = Member::factory()->create();
        $loan = Loan::factory()->create(['member_id' => $member->id]);

        $this->assertTrue($member->loans->contains($loan));
        $this->assertInstanceOf(Loan::class, $member->loans->first());
    }

    public function test_member_has_many_transactions(): void
    {
        $member = Member::factory()->create();
        $transaction = Transaction::factory()->savingDeposit()->create(['member_id' => $member->id]);

        $this->assertTrue($member->transactions->contains($transaction));
        $this->assertInstanceOf(Transaction::class, $member->transactions->first());
    }

    public function test_loan_belongs_to_member(): void
    {
        $member = Member::factory()->create();
        $loan = Loan::factory()->create(['member_id' => $member->id]);

        $this->assertEquals($member->id, $loan->member->id);
        $this->assertInstanceOf(Member::class, $loan->member);
    }

    public function test_loan_has_many_transactions(): void
    {
        $member = Member::factory()->create();
        $loan = Loan::factory()->create(['member_id' => $member->id]);
        $transaction = Transaction::factory()->loanRepayment()->create([
            'member_id' => $member->id,
            'loan_id' => $loan->id
        ]);

        $this->assertTrue($loan->transactions->contains($transaction));
    }

    public function test_transaction_belongs_to_member(): void
    {
        $member = Member::factory()->create();
        $transaction = Transaction::factory()->create(['member_id' => $member->id]);

        $this->assertEquals($member->id, $transaction->member->id);
        $this->assertInstanceOf(Member::class, $transaction->member);
    }

    public function test_transaction_belongs_to_loan(): void
    {
        $member = Member::factory()->create();
        $loan = Loan::factory()->create(['member_id' => $member->id]);
        $transaction = Transaction::factory()->loanRepayment()->create([
            'member_id' => $member->id,
            'loan_id' => $loan->id
        ]);

        $this->assertEquals($loan->id, $transaction->loan->id);
        $this->assertInstanceOf(Loan::class, $transaction->loan);
    }

    public function test_member_total_debt_accessor(): void
    {
        $member = Member::factory()->create();
        
        Loan::factory()->active()->create([
            'member_id' => $member->id,
            'amount' => 1000000,
            'remaining_principal' => 500000,
        ]);
        
        Loan::factory()->active()->create([
            'member_id' => $member->id,
            'amount' => 2000000,
            'remaining_principal' => 1500000,
        ]);

        $this->assertEquals(2000000, $member->fresh()->total_debt);
    }

    public function test_loan_monthly_principal_accessor(): void
    {
        $loan = Loan::factory()->create([
            'amount' => 1200000,
            'duration' => 12,
        ]);

        $this->assertEquals(100000, $loan->monthly_principal);
    }

    public function test_loan_reduce_remaining_principal(): void
    {
        $loan = Loan::factory()->fresh()->create([
            'amount' => 1000000,
            'remaining_principal' => 1000000,
        ]);

        $loan->reduceRemainingPrincipal(200000);

        $this->assertEquals(800000, $loan->fresh()->remaining_principal);
        $this->assertEquals('active', $loan->fresh()->status);
    }

    public function test_loan_mark_as_paid_when_fully_paid(): void
    {
        $loan = Loan::factory()->create([
            'amount' => 1000000,
            'remaining_principal' => 100000,
        ]);

        $loan->reduceRemainingPrincipal(100000);

        $this->assertEquals(0, $loan->fresh()->remaining_principal);
        $this->assertEquals('paid', $loan->fresh()->status);
    }
}
