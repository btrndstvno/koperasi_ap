<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SettingHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /**
     * Display the settings page.
     */
    public function index()
    {
        $settings = [
            'saving_interest_rate' => Setting::get('saving_interest_rate', 0.5),
            'shu_rate' => Setting::get('shu_rate', 5),
            'loan_interest_rate' => Setting::get('loan_interest_rate', 1),
        ];

        $histories = SettingHistory::with('changedByUser')
            ->orderByDesc('effective_from')
            ->limit(20)
            ->get();

        return view('settings.index', compact('settings', 'histories'));
    }

    /**
     * Update settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'saving_interest_rate' => 'required|numeric|min:0|max:100',
            'shu_rate' => 'required|numeric|min:0|max:100',
            'loan_interest_rate' => 'required|numeric|min:0|max:100',
        ]);

        $userId = Auth::id();

        Setting::set('saving_interest_rate', $validated['saving_interest_rate'], $userId);
        Setting::set('shu_rate', $validated['shu_rate'], $userId);
        Setting::set('loan_interest_rate', $validated['loan_interest_rate'], $userId);

        return back()->with('success', 'Pengaturan berhasil disimpan. Perubahan berlaku untuk transaksi baru.');
    }
}
