<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingHistory extends Model
{
    protected $fillable = [
        'key',
        'old_value',
        'new_value',
        'changed_by',
        'effective_from',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
    ];

    /**
     * Get the user who changed this setting.
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get human-readable key name.
     */
    public function getKeyLabelAttribute(): string
    {
        return match ($this->key) {
            'saving_interest_rate' => 'Bunga Tabungan',
            'shu_rate' => 'Bunga SHU',
            'loan_interest_rate' => 'Bunga Pinjaman',
            default => $this->key,
        };
    }
}
