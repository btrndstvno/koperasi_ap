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
        'savings_balance',
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
}
