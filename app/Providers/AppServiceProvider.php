<?php

namespace App\Providers;

use App\Models\Loan;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share pending counts with all views (sidebar needs this)
        View::composer('*', function ($view) {
            if (\Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->isAdmin()) {
                $view->with('globalPendingLoans', Loan::where('status', 'pending')->count());
                $view->with('globalPendingWithdrawals', Withdrawal::where('status', 'pending')->count());
            }
        });
    }
}
