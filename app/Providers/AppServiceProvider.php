<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        // Admin da IA: pode trocar o provider global de geração de imagem.
        Gate::define('manage-ai', fn (User $user) => str_starts_with($user->email, 'italorzuliani'));
    }
}
