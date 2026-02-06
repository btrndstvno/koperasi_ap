<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
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
// AUTHENTICATED ROUTES (All Users)
// ============================================================
Route::middleware('auth')->group(function () {
    // Dashboard (Role-based redirect)
    Route::get('/', fn() => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile Settings
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Member can submit loan application
    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
    Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');

    // Member Savings (Riwayat Pembayaran/Tabungan)
    Route::get('/my-savings', [TransactionController::class, 'mySavings'])->name('members.my-savings');
});

// ============================================================
// ADMIN ROUTES (Admin Only)
// ============================================================
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Reports (Moved all reports here to be safe)
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
    
    // Members Management
    Route::resource('members', MemberController::class);
    Route::get('/members-search', [MemberController::class, 'searchMembers'])->name('members.search');
    Route::post('/members/{member}/add-saving', [MemberController::class, 'addSaving'])->name('members.add-saving');
    Route::post('/members/{member}/withdraw-saving', [MemberController::class, 'withdrawSaving'])->name('members.withdraw-saving');

    // Loans Management
    // Route::get('/loans', [LoanController::class, 'index'])->name('loans.index'); // Moved to shared routes
    Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
    Route::post('/loans/{loan}/repay', [LoanController::class, 'repay'])->name('loans.repay');
    Route::post('/loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve');
    Route::post('/loans/{loan}/reject', [LoanController::class, 'reject'])->name('loans.reject');
    Route::get('/loans/{loan}/print', [LoanController::class, 'print'])->name('loans.print');

    // Bulk Transactions
    Route::get('/transactions/bulk', [TransactionController::class, 'createBulk'])->name('transactions.bulk.create');
    Route::post('/transactions/bulk', [TransactionController::class, 'storeBulk'])->name('transactions.bulk.store');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    // Import Excel
    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('/imports/preview', [ImportController::class, 'preview'])->name('imports.preview');
    Route::post('/imports/process', [ImportController::class, 'process'])->name('imports.process');

    // Exports
    Route::get('/exports/members', [ExportController::class, 'members'])->name('exports.members');
});
