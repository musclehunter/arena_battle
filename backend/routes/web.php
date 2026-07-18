<?php

use App\Http\Controllers\BattleController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\GuestHireController;
use App\Http\Controllers\HiringController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HouseController;
use App\Http\Controllers\JobSeekerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PartyBattleController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\MasterController;
use Illuminate\Support\Facades\Route;

// --- 公開ルート(ゲスト / 認証 両対応) -----------------------------
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/job-seekers', [JobSeekerController::class, 'index'])->name('job-seekers.index');

// マスタデータAPI（認証不要、キャッシュ付き）
Route::get('/api/master', [MasterController::class, 'index'])->name('master.index');
Route::post('/api/master/bump', [MasterController::class, 'bump'])->name('master.bump');
Route::post('/api/master/invalidate', [MasterController::class, 'invalidate'])->name('master.invalidate');
Route::post('/api/master/clear-invalidate', [MasterController::class, 'clearInvalidate'])->name('master.clear-invalidate');

// ゲスト雇用 → バトル自動開始 (未認証可)
Route::post('/guest-hires', [GuestHireController::class, 'store'])->name('guest-hires.store');

// バトル(所有者チェックは BattlePolicy で実施)
Route::get('/battles/{battle}', [BattleController::class, 'show'])->name('battles.show');
Route::post('/battles/{battle}/turn', [BattleController::class, 'resolveTurn'])->name('battles.turn');
Route::post('/battles/{battle}/restart', [BattleController::class, 'restart'])->name('battles.restart');

// --- 認証必須 + メール認証済: 家門機能 ---------------------------
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/houses/create', [HouseController::class, 'create'])->name('houses.create');
    Route::post('/houses', [HouseController::class, 'store'])->name('houses.store');
    Route::get('/houses/mine', [HouseController::class, 'mine'])->name('houses.mine');
    Route::get('/parties/edit', [PartyController::class, 'edit'])->name('parties.edit');
    Route::put('/parties', [PartyController::class, 'update'])->name('parties.update');
    Route::get('/party-battles/select', [PartyBattleController::class, 'select'])->name('party-battles.select');
    Route::post('/party-battles', [PartyBattleController::class, 'store'])->name('party-battles.store');
    Route::get('/party-battles/{partyBattle}', [PartyBattleController::class, 'show'])->name('party-battles.show');
    Route::get('/party-battles/{partyBattle}/result', [PartyBattleController::class, 'result'])->name('party-battles.result');
    Route::get('/party-battles/{partyBattle}/state', [PartyBattleController::class, 'state'])->name('party-battles.state');
    Route::post('/party-battles/{partyBattle}/actions', [PartyBattleController::class, 'reserve'])->name('party-battles.actions');
    Route::post('/party-battles/{partyBattle}/auto', [PartyBattleController::class, 'auto'])->name('party-battles.auto');

    Route::get('/characters', [CharacterController::class, 'index'])->name('characters.index');
    Route::get('/characters/{character}', [CharacterController::class, 'show'])->name('characters.show');

    Route::get('/skills', [SkillController::class, 'index'])->name('skills.index');
    Route::post('/characters/{character}/skills/learn', [SkillController::class, 'learn'])->name('skills.learn');

    Route::get('/market', fn () => \Inertia\Inertia::render('Market/Index'))->name('market.index');
    Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
    Route::get('/production/state', [ProductionController::class, 'state'])->name('production.state');
    Route::post('/production/jobs', [ProductionController::class, 'start'])->name('production.jobs.start');
    Route::post('/production/jobs/{productionJob}/collect', [ProductionController::class, 'collect'])->name('production.jobs.collect');
    Route::get('/blood-pact', fn () => \Inertia\Inertia::render('BloodPact/Index'))->name('blood-pact.index');
    Route::get('/settings', fn () => \Inertia\Inertia::render('Settings/Index'))->name('settings.index');

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
