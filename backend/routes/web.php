<?php

use App\Http\Controllers\BattleController;
use App\Http\Controllers\GuestHireController;
use App\Http\Controllers\HiringController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HouseController;
use App\Http\Controllers\JobSeekerController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// --- 公開ルート(ゲスト / 認証 両対応) -----------------------------
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/job-seekers', [JobSeekerController::class, 'index'])->name('job-seekers.index');

// ゲスト雇用 → バトル自動開始 (未認証可)
Route::post('/guest-hires', [GuestHireController::class, 'store'])->name('guest-hires.store');

// バトル(所有者チェックは BattlePolicy で実施)
Route::get('/battles/{battle}', [BattleController::class, 'show'])->name('battles.show');
Route::post('/battles/{battle}/turn', [BattleController::class, 'resolveTurn'])->name('battles.turn');
Route::post('/battles/{battle}/restart', [BattleController::class, 'restart'])->name('battles.restart');

// --- 認証必須: 家門機能 ------------------------------------------
Route::middleware('auth')->group(function () {
    Route::get('/houses/create', [HouseController::class, 'create'])->name('houses.create');
    Route::post('/houses', [HouseController::class, 'store'])->name('houses.store');
    Route::get('/houses/mine', [HouseController::class, 'mine'])->name('houses.mine');

    Route::post('/houses/hire', [HiringController::class, 'store'])->name('houses.hire');
    Route::post('/houses/release/{character}', [HiringController::class, 'destroy'])->name('houses.release');

    // 家門プレイヤーが自家門のキャラでバトル開始
    Route::post('/battles', [BattleController::class, 'store'])->name('battles.store');

    // Breeze の auth フローが dashboard 名で redirect してくるため、家門ダッシュボードへエイリアス。
    Route::get('/dashboard', fn () => redirect()->route('houses.mine'))->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
