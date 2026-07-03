<?php

use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\GenerateController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScrapeController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('feed.index')
        : redirect()->route('login');
})->name('home');

// O starter kit redireciona para "dashboard" após o login.
Route::get('dashboard', fn () => redirect()->route('feed.index'))
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('feed', [FeedController::class, 'index'])->name('feed.index');

    Route::get('criar', fn () => Inertia::render('Editor'))->name('editor');

    Route::post('scrape', [ScrapeController::class, 'store'])->name('scrape');
    Route::post('generate', [GenerateController::class, 'store'])->name('generate');

    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
    Route::post('posts/{post}/like', [PostController::class, 'toggleLike'])->name('posts.like');

    Route::get('perfil/{user}', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('buscar', [SearchController::class, 'index'])->name('search');

    Route::put('admin/ai-provider', [AiSettingsController::class, 'update'])
        ->middleware('can:manage-ai')
        ->name('admin.ai-provider');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
