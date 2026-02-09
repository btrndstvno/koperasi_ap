<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'description'];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, $default = null)
    {
        $setting = Cache::remember("setting_{$key}", 3600, function () use ($key) {
            return self::where('key', $key)->first();
        });

        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value (with history tracking).
     */
    public static function set(string $key, $value, ?int $changedBy = null): void
    {
        $setting = self::where('key', $key)->first();
        
        if ($setting) {
            $oldValue = $setting->value;
            
            // Only create history if value actually changed
            if ($oldValue != $value) {
                SettingHistory::create([
                    'key' => $key,
                    'old_value' => $oldValue,
                    'new_value' => $value,
                    'changed_by' => $changedBy,
                    'effective_from' => now(),
                ]);
            }
            
            $setting->update(['value' => $value]);
        } else {
            self::create([
                'key' => $key,
                'value' => $value,
            ]);
        }

        // Clear cache
        Cache::forget("setting_{$key}");
    }

    /**
     * Get the rate that was effective at a specific date.
     */
    public static function getRateAtDate(string $key, $date)
    {
        $date = \Carbon\Carbon::parse($date);
        
        // Find the last history entry before or at the given date
        $history = SettingHistory::where('key', $key)
            ->where('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();

        if ($history) {
            return (float) $history->new_value;
        }

        // No history found, return current setting value
        return (float) self::get($key, 0);
    }

    /**
     * Get saving interest rate.
     */
    public static function getSavingInterestRate(): float
    {
        return (float) self::get('saving_interest_rate', 0.5);
    }

    /**
     * Get SHU rate.
     */
    public static function getShuRate(): float
    {
        return (float) self::get('shu_rate', 5);
    }

    /**
     * Get loan interest rate.
     */
    public static function getLoanInterestRate(): float
    {
        return (float) self::get('loan_interest_rate', 1);
    }
}
