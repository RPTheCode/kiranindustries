<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Plan;
use App\Observers\UserObserver;
use App\Observers\PlanObserver;
use App\Providers\AssetServiceProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\WebhookService::class);
        
        // Register our AssetServiceProvider
        $this->app->register(AssetServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Console (scheduler/queue): use company IST, not PHP.ini server timezone
        if ($this->app->runningInConsole() && file_exists(storage_path('installed'))) {
            try {
                \App\Services\EsslAutoSyncConfig::applyCompanyTimezone();
            } catch (\Throwable) {
                config(['app.timezone' => 'Asia/Kolkata']);
                date_default_timezone_set('Asia/Kolkata');
            }
        }

        // Force HTTPS in non-local environments (staging/production behind reverse proxy)
        if (!$this->app->environment('local')) {
            URL::forceScheme('https');
        }

        // Register the UserObserver
        User::observe(UserObserver::class);
        
        // Register the PlanObserver
        Plan::observe(PlanObserver::class);

        // Strict Email Validation
        \Illuminate\Support\Facades\Validator::extend('strict_email', function ($attribute, $value, $parameters, $validator) {
            // Check basic format
            if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $value)) {
                return false;
            }

            // Get trusted domains from settings (comma-separated: domain1.com, domain2.com)
            $trustedDomains = getSetting('trusted_domains');
            if (!$trustedDomains) {
                return true; // Allow all valid formats if nothing is configured
            }

            $allowedDomains = array_map('trim', explode(',', strtolower($trustedDomains)));
            $domain = strtolower(substr(strrchr($value, "@"), 1));

            return in_array($domain, $allowedDomains);
        }, __('The :attribute must be a valid email address from a trusted domain.'));

        // Strict Phone Validation
        \Illuminate\Support\Facades\Validator::extend('strict_phone', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^\+?[0-9]{10,15}$/', $value);
        }, __('The :attribute must be a valid phone number (10-15 digits, optional + prefix).'));

        // Configure dynamic storage disks
        try {
            // \App\Services\DynamicStorageService::configureDynamicDisks();
        } catch (\Exception $e) {
            // Silently fail during migrations or when database is not ready
        }

        // Implicitly grant "Super Admin" and "Company" role all permissions
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            if (in_array($user->type, ['superadmin', 'super admin', 'company'])) {
                return true;
            }
        });
    }
}