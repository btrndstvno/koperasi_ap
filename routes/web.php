<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Auth Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Redirect root to dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('auth');

// Members
Route::middleware(['auth'])->group(function () {
    Route::resource('members', MemberController::class);
    Route::get('/members-search', [MemberController::class, 'searchMembers'])->name('members.search');
    Route::post('/members/{member}/add-saving', [MemberController::class, 'addSaving'])->name('members.add-saving');
    Route::post('/members/{member}/withdraw-saving', [MemberController::class, 'withdrawSaving'])->name('members.withdraw-saving');

    // Loans
    Route::resource('loans', LoanController::class)->except(['edit', 'update', 'destroy']);
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
    
    // Experts
    // Add export routes here if needed
    Route::get('/exports/members', [ExportController::class, 'members'])->name('exports.members');
});

// Reports
Route::get('/reports', [ReportController::class, 'index'])->name('reports.index')->middleware(['auth']);
Route::get('/reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly')->middleware(['auth', 'role:admin']);

Route::get('/exports/members', [ExportController::class, 'members'])->name('exports.members');
