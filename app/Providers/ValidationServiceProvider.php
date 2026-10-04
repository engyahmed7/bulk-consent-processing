<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerMacros();
    }

    /**
     * Register custom validation macros for bulk operations.
     */
    protected function registerMacros(): void
    {
        Validator::extend('bulk_userid', function ($attribute, $value) {
            return ! empty(trim((string) $value));
        });

        Validator::extend('bulk_phonenumber', function ($attribute, $value) {
            $normalized = preg_replace('/[\s\-()]/', '', (string) $value);
            return preg_match('/^\+?[0-9]{8,15}$/', $normalized);
        });
    }
}
