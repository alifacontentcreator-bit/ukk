<?php
/* Tujuan file: Provider ini mendaftarkan atau menyiapkan layanan aplikasi app service provider saat Laravel dijalankan. */


namespace App\Providers;

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
        \App\Models\User::observe(\App\Observers\AuditObserver::class);
        \App\Models\Account::observe(\App\Observers\AuditObserver::class);
        \App\Models\Transaction::observe(\App\Observers\AuditObserver::class);
    }
}
