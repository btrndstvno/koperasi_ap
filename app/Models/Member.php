<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nik',
        'name',
        'group_tag',
        'csd',
        'dept',
        'employee_status',
        'is_active',
        'deactivated_at',
        'savings_balance',
        'is_active',
        'deactivation_reason',
        'deactivation_date',
        'final_savings_balance',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'savings_balance' => 'decimal:2',
        'employee_status' => 'string',
        'group_tag' => 'string',
        'is_active' => 'boolean',
        'deactivated_at' => 'datetime',
    ];

    /**
     * Group tag constants
     */
    const GROUP_MANAGER = 'Manager';
    const GROUP_BANGUNAN = 'Bangunan';
    const GROUP_CSD = 'CSD';
    const GROUP_OFFICE = 'Office';

    /**
     * Get available group tags
     */
    public static function getGroupTags(): array
    {
        return [
            self::GROUP_MANAGER,
            self::GROUP_BANGUNAN,
            self::GROUP_CSD,
            self::GROUP_OFFICE,
        ];
    }

    /**
     * Get all loans for the member.
     * Relasi: Member memiliki banyak Pinjaman
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Get all transactions for the member.
     * Relasi: Member memiliki banyak Transaksi
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get active loans for the member.
     * Scope: Hanya pinjaman yang masih aktif
     */
    public function activeLoans(): HasMany
    {
        return $this->hasMany(Loan::class)->where('status', 'active');
    }

    /**
     * Get total remaining principal from all active loans.
     * Accessor: Total sisa hutang dari semua pinjaman aktif
     */
    public function getTotalDebtAttribute(): float
    {
        return (float) $this->activeLoans()->sum('remaining_principal');
    }

    /**
     * Check if member has active loan.
     */
    public function hasActiveLoan(): bool
    {
        return $this->activeLoans()->exists();
    }

    /**
     * Scope: Filter by department
     */
    public function scopeByDepartment($query, string $dept)
    {
        return $query->where('dept', $dept);
    }

    /**
     * Scope: Filter by employee status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('employee_status', $status);
    }

    /**
     * Scope: Members with active loans
     */
    public function scopeWithActiveLoans($query)
    {
        return $query->whereHas('loans', function ($q) {
            $q->where('status', 'active');
        });
    }

    /**
     * Get all withdrawals for the member.
     * Relasi: Member memiliki banyak Penarikan
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /**
     * Scope: Only active members
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Only inactive members
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope: Filter by group tag
     */
    public function scopeByGroupTag($query, string $groupTag)
    {
        return $query->where('group_tag', $groupTag);
    }

    /**
     * Deactivate member and withdraw all savings
     */
    public function deactivate(): float
    {
        $withdrawnAmount = $this->savings_balance;
        
        // Create withdrawal transaction if has balance
        if ($withdrawnAmount > 0) {
            Transaction::create([
                'member_id' => $this->id,
                'loan_id' => null,
                'transaction_date' => now()->format('Y-m-d'),
                'type' => Transaction::TYPE_SAVING_WITHDRAW,
                'amount_saving' => $withdrawnAmount,
                'amount_principal' => 0,
                'amount_interest' => 0,
                'total_amount' => $withdrawnAmount,
                'payment_method' => 'cash',
                'notes' => 'Penarikan otomatis - Anggota dinonaktifkan',
            ]);
            
            $this->savings_balance = 0;
        }
        
        $this->is_active = false;
        $this->deactivated_at = now();
        $this->save();
        
        return $withdrawnAmount;
    }

    /**
     * Reactivate member
     */
    public function reactivate(): void
    {
        $this->is_active = true;
        $this->deactivated_at = null;
        $this->save();
    }
}
