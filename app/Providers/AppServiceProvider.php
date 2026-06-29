<?php

namespace App\Providers;

use App\Models\Banner;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('access-pos', fn ($user) => in_array($user->role, ['admin', 'cashier']));

        View::composer('layouts.store', function ($view) {
            $view->with('announcements', Banner::active()->where('type', 'announcement')->get());
            $view->with('popups', Banner::active()->where('type', 'popup')->get());
            $view->with('settings', [
                'store_address' => Setting::get('store_address'),
                'phone' => Setting::get('phone'),
                'whatsapp' => Setting::get('whatsapp'),
                'email' => Setting::get('email'),
                'instagram' => Setting::get('instagram'),
                'facebook' => Setting::get('facebook'),
                'tiktok' => Setting::get('tiktok'),
                'bank_accounts' => Setting::get('bank_accounts'),
            ]);
        });
    }
}
