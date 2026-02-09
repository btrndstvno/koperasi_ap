<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'member_id',
        'amount',
        'interest_rate',
        'duration',
        'remaining_principal',
        'monthly_installment',
        'total_interest',
        'admin_fee',
        'disbursed_amount',
        'status',
        'application_date',
        'approved_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'interest_rate' => 'float',
        'duration' => 'integer',
        'remaining_principal' => 'decimal:2',
        'monthly_installment' => 'decimal:2',
        'total_interest' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'disbursed_amount' => 'decimal:2',
        'status' => 'string',
        'application_date' => 'date',
        'approved_date' => 'date',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_PAID = 'paid';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get status label in Indonesian
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu Persetujuan',
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_PAID => 'Lunas',
            self::STATUS_REJECTED => 'Ditolak',
            default => 'Unknown',
        };
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_ACTIVE => 'primary',
            self::STATUS_PAID => 'success',
            self::STATUS_REJECTED => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Check if loan is pending approval
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if loan is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Get the member that owns the loan.
     * Relasi: Pinjaman dimiliki oleh satu Member
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get all transactions for this loan.
     * Relasi: Pinjaman memiliki banyak Transaksi pembayaran
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get repayment transactions only.
     */
    public function repayments(): HasMany
    {
        return $this->hasMany(Transaction::class)->where('type', 'loan_repayment');
    }

    /**
     * Calculate monthly installment (Pokok per bulan).
     * Untuk sistem "Bunga Potong di Awal", cicilan = Pokok / Tenor
     * Gunakan nilai dari database jika tersedia (untuk backward compatibility)
     */
    public function getMonthlyPrincipalAttribute(): float
    {
        // Prioritas: gunakan monthly_installment dari database jika ada
        if ($this->attributes['monthly_installment'] ?? 0 > 0) {
            return (float) $this->attributes['monthly_installment'];
        }
        
        // Fallback: hitung manual
        if ($this->duration <= 0) {
            return 0;
        }
        return round((float) $this->amount / $this->duration, 2);
    }

    /**
     * Calculate monthly interest amount.
     * Untuk sistem "Bunga Potong di Awal": Bunga sudah lunas, jadi monthly interest = 0
     * Untuk loan lama (backward compatibility): gunakan formula lama
     */
    public function getMonthlyInterestAttribute(): float
    {
        // Jika menggunakan sistem bunga di awal (total_interest > 0), bunga bulanan = 0
        if (($this->attributes['total_interest'] ?? 0) > 0) {
            return 0;
        }
        
        // Fallback untuk loan lama: Bunga per Bulan = (Sisa Pokok * Interest Rate) / 100
        return round(((float) $this->remaining_principal * $this->interest_rate) / 100, 2);
    }

    /**
     * Check if this loan uses "Bunga Potong di Awal" system.
     */
    public function isUpfrontInterest(): bool
    {
        return ($this->attributes['total_interest'] ?? 0) > 0;
    }

    /**
     * Calculate total monthly payment (Pokok + Bunga).
     */
    public function getMonthlyPaymentAttribute(): float
    {
        return $this->monthly_principal + $this->monthly_interest;
    }

    /**
     * Get total principal paid.
     */
    public function getTotalPrincipalPaidAttribute(): float
    {
        return (float) $this->amount - (float) $this->remaining_principal;
    }

    /**
     * Get payment progress percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ((float) $this->amount <= 0) {
            return 0;
        }
        return round(($this->total_principal_paid / (float) $this->amount) * 100, 2);
    }

    /**
     * Check if loan is fully paid.
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID || (float) $this->remaining_principal <= 0;
    }

    /**
     * Check if loan is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && (float) $this->remaining_principal > 0;
    }

    /**
     * Mark loan as paid.
     */
    public function markAsPaid(): bool
    {
        $this->status = self::STATUS_PAID;
        $this->remaining_principal = 0;
        return $this->save();
    }

    /**
     * Calculate remaining installments count.
     * Sisa kali bayar = ceil(Sisa Pokok / Cicilan Pokok per Bulan)
     */
    public function getRemainingInstallmentsAttribute(): int
    {
        $installment = $this->monthly_principal;
        
        if ($installment <= 0 || $this->remaining_principal <= 0) {
            return 0;
        }

        return (int) ceil($this->remaining_principal / $installment);
    }

    /**
     * Reduce remaining principal by given amount.
     */
    public function reduceRemainingPrincipal(float $amount): bool
    {
        $newRemaining = (float) $this->remaining_principal - $amount;
        
        if ($newRemaining <= 0) {
            return $this->markAsPaid();
        }

        $this->remaining_principal = $newRemaining;
        return $this->save();
    }

    /**
     * Scope: Active loans only
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: Paid loans only
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }
}
