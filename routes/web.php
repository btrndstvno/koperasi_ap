<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Semua route (kecuali login) hanya bisa diakses oleh Admin.
| Member tidak memiliki akses ke sistem.
*/

// ============================================================
// AUTH ROUTES (Guest Only)
// ============================================================
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ============================================================
// ADMIN ROUTES (Admin Only - All Features)
// ============================================================
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Dashboard
    Route::get('/', fn() => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile Settings
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // System Settings (Bunga)
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
    Route::get('/reports/shu', [ReportController::class, 'shu'])->name('reports.shu');
    Route::post('/reports/shu/distribute', [ReportController::class, 'distributeSHU'])->name('reports.shu.distribute');
    
    // Members Management
    Route::get('/members/inactive', [MemberController::class, 'inactiveIndex'])->name('members.inactive');
    Route::resource('members', MemberController::class);
    Route::get('/members-search', [MemberController::class, 'searchMembers'])->name('members.search');
    Route::post('/members/{member}/add-saving', [MemberController::class, 'addSaving'])->name('members.add-saving');
    Route::post('/members/{member}/withdraw-saving', [MemberController::class, 'withdrawSaving'])->name('members.withdraw-saving');
    Route::post('/members/{member}/activate', [MemberController::class, 'activate'])->name('members.activate');
    Route::post('/members/{member}/deactivate', [MemberController::class, 'deactivate'])->name('members.deactivate');
   

    // Loans Management
    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
    Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
    Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
    Route::post('/loans/{loan}/repay', [LoanController::class, 'repay'])->name('loans.repay');
    Route::post('/loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve');
    Route::put('/loans/{loan}/update-amount', [LoanController::class, 'updateAmount'])->name('loans.update-amount');
    Route::post('/loans/{loan}/reject', [LoanController::class, 'reject'])->name('loans.reject');
    Route::get('/loans/{loan}/print', [LoanController::class, 'print'])->name('loans.print');
    Route::post('/loans/{loan}/write-off', [LoanController::class, 'writeOff'])->name('loans.write-off');

    // Withdrawals Management (Admin processes all withdrawals)
    Route::get('/withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::get('/withdrawals/create', [WithdrawalController::class, 'create'])->name('withdrawals.create');
    Route::post('/withdrawals', [WithdrawalController::class, 'store'])->name('withdrawals.store');
    Route::get('/withdrawals/{withdrawal}', [WithdrawalController::class, 'show'])->name('withdrawals.show');
    Route::post('/withdrawals/{withdrawal}/approve', [WithdrawalController::class, 'approve'])->name('withdrawals.approve');
    Route::post('/withdrawals/{withdrawal}/reject', [WithdrawalController::class, 'reject'])->name('withdrawals.reject');
    Route::get('/withdrawals/{withdrawal}/print', [WithdrawalController::class, 'print'])->name('withdrawals.print');

    // Bulk Transactions
    Route::get('/transactions/bulk', [TransactionController::class, 'createBulk'])->name('transactions.bulk.create');
    Route::post('/transactions/bulk', [TransactionController::class, 'storeBulk'])->name('transactions.bulk.store');
    Route::post('/transactions/bulk/update-single', [TransactionController::class, 'updateSingleTransaction'])->name('transactions.bulk.update-single');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    // Import Excel
    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('/imports/preview', [ImportController::class, 'preview'])->name('imports.preview');
    Route::post('/imports/process', [ImportController::class, 'process'])->name('imports.process');
    Route::post('/imports/direct', [ImportController::class, 'direct'])->name('imports.direct');

    // Exports
    Route::get('/exports/members', [ExportController::class, 'members'])->name('exports.members');
    Route::get('/exports/pending-loans', [ExportController::class, 'pendingLoans'])->name('exports.pending-loans');
    Route::get('/exports/withdrawals', [ExportController::class, 'withdrawals'])->name('exports.withdrawals');
    Route::get('/exports/loans', [ExportController::class, 'loans'])->name('exports.loans');
});
