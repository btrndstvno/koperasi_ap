<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'member_id',
        'loan_id',
        'transaction_date',
        'type',
        'amount_saving',
        'amount_principal',
        'amount_interest',
        'total_amount',
        'payment_method',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'transaction_date' => 'date',
        'amount_saving' => 'decimal:2',
        'amount_principal' => 'decimal:2',
        'amount_interest' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'type' => 'string',
        'payment_method' => 'string',
    ];

    /**
     * Transaction type constants
     */
    const TYPE_SAVING_DEPOSIT = 'saving_deposit';       // Setoran Simpanan
    const TYPE_LOAN_REPAYMENT = 'loan_repayment';       // Pembayaran Pinjaman
    const TYPE_SAVING_WITHDRAW = 'saving_withdraw';     // Penarikan Simpanan
    const TYPE_INTEREST_REVENUE = 'interest_revenue';   // Pendapatan Bunga
    const TYPE_ADMIN_FEE = 'admin_fee';                 // Biaya Admin 1%
    const TYPE_LOAN_DISBURSEMENT = 'loan_disbursement'; // Pencairan Pinjaman
    const TYPE_SAVING_INTEREST = 'saving_interest';     // Bunga Tabungan (Auto Monthly)
    const TYPE_SHU_REWARD = 'shu_reward';               // Pembagian SHU (Annual)
    const TYPE_LOAN_WRITE_OFF = 'loan_write_off';       // Penghapusan Piutang

    /**
     * Get the member that owns the transaction.
     * Relasi: Transaksi dimiliki oleh satu Member
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the loan associated with the transaction.
     * Relasi: Transaksi bisa terkait dengan satu Pinjaman (nullable)
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get transaction type label in Indonesian.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_SAVING_DEPOSIT => 'Setoran Simpanan',
            self::TYPE_LOAN_REPAYMENT => 'Pembayaran Pinjaman',
            self::TYPE_SAVING_WITHDRAW => 'Penarikan Simpanan',
            self::TYPE_INTEREST_REVENUE => 'Pendapatan Bunga',
            self::TYPE_ADMIN_FEE => 'Biaya Admin',
            self::TYPE_LOAN_DISBURSEMENT => 'Pencairan Pinjaman',
            self::TYPE_SAVING_INTEREST => 'Bunga Tabungan',
            self::TYPE_SHU_REWARD => 'Pembagian SHU',
            default => 'Unknown',
        };
    }

    /**
     * Get transaction type badge color for UI.
     */
    public function getTypeBadgeColorAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_SAVING_DEPOSIT => 'success',
            self::TYPE_LOAN_REPAYMENT => 'primary',
            self::TYPE_SAVING_WITHDRAW => 'warning',
            self::TYPE_INTEREST_REVENUE => 'info',
            self::TYPE_ADMIN_FEE => 'secondary',
            self::TYPE_LOAN_DISBURSEMENT => 'dark',
            self::TYPE_SAVING_INTEREST => 'info',
            self::TYPE_SHU_REWARD => 'success',
            default => 'secondary',
        };
    }

    /**
     * Check if this is a debit transaction (money coming in).
     */
    public function isDebit(): bool
    {
        return in_array($this->type, [
            self::TYPE_SAVING_DEPOSIT,
            self::TYPE_LOAN_REPAYMENT,
        ]);
    }

    /**
     * Check if this is a credit transaction (money going out).
     */
    public function isCredit(): bool
    {
        return $this->type === self::TYPE_SAVING_WITHDRAW;
    }

    /**
     * Calculate total amount before saving.
     */
    public function calculateTotalAmount(): float
    {
        return (float) $this->amount_saving 
             + (float) $this->amount_principal 
             + (float) $this->amount_interest;
    }

    /**
     * Boot method to auto-calculate total_amount.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($transaction) {
            // Auto-calculate total_amount if not set
            if (empty($transaction->total_amount)) {
                $transaction->total_amount = $transaction->calculateTotalAmount();
            }
        });
    }

    /**
     * Scope: Filter by transaction type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Filter by month and year
     */
    public function scopeInMonth($query, int $month, int $year)
    {
        return $query->whereMonth('transaction_date', $month)
                     ->whereYear('transaction_date', $year);
    }

    /**
     * Scope: Saving transactions only (deposit & withdraw)
     */
    public function scopeSavingTransactions($query)
    {
        return $query->whereIn('type', [
            self::TYPE_SAVING_DEPOSIT,
            self::TYPE_SAVING_WITHDRAW,
        ]);
    }

    /**
     * Scope: Loan transactions only
     */
    public function scopeLoanTransactions($query)
    {
        return $query->where('type', self::TYPE_LOAN_REPAYMENT);
    }

    /**
     * Create a saving deposit transaction.
     */
    public static function createSavingDeposit(
        int $memberId,
        float $amount,
        string $date,
        ?string $notes = null
    ): self {
        return self::create([
            'member_id' => $memberId,
            'loan_id' => null,
            'transaction_date' => $date,
            'type' => self::TYPE_SAVING_DEPOSIT,
            'amount_saving' => $amount,
            'amount_principal' => 0,
            'amount_interest' => 0,
            'total_amount' => $amount,
            'notes' => $notes,
        ]);
    }

    /**
     * Create a loan repayment transaction.
     */
    public static function createLoanRepayment(
        int $memberId,
        int $loanId,
        float $principalAmount,
        float $interestAmount,
        string $date,
        ?string $notes = null
    ): self {
        return self::create([
            'member_id' => $memberId,
            'loan_id' => $loanId,
            'transaction_date' => $date,
            'type' => self::TYPE_LOAN_REPAYMENT,
            'amount_saving' => 0,
            'amount_principal' => $principalAmount,
            'amount_interest' => $interestAmount,
            'total_amount' => $principalAmount + $interestAmount,
            'notes' => $notes,
        ]);
    }
}
