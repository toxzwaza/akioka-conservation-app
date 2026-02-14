<?php

use App\Http\Controllers\ApiTestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\MasterEquipmentController;
use App\Http\Controllers\MasterPartController;
use App\Http\Controllers\MasterUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 未認証時は /login へ
Route::get('/', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// 作業関係 /work
Route::middleware(['auth', 'verified'])->prefix('work')->name('work.')->group(function () {
    Route::get('parts/search', [WorkController::class, 'searchParts'])->name('parts.search');
    Route::resource('works', WorkController::class)->only(['index', 'create', 'store', 'show', 'update']);
    Route::post('works/{work}/work-contents', [WorkController::class, 'storeWorkContent'])->name('works.work-contents.store');
    Route::post('works/{work}/work-used-parts', [WorkController::class, 'storeWorkUsedPart'])->name('works.work-used-parts.store');
    Route::post('works/{work}/work-costs', [WorkController::class, 'storeWorkCost'])->name('works.work-costs.store');
    Route::post('works/{work}/comments', [WorkController::class, 'storeComment'])->name('works.comments.store');
    Route::put('works/{work}/comments/{activity}', [WorkController::class, 'updateComment'])->name('works.comments.update');
    Route::delete('works/{work}/comments/{activity}', [WorkController::class, 'destroyComment'])->name('works.comments.destroy');
});

// APIテスト（Conservation API）
Route::middleware(['auth', 'verified'])->prefix('api-test')->name('api-test.')->group(function () {
    Route::get('/', [ApiTestController::class, 'index'])->name('index');
    Route::post('/execute', [ApiTestController::class, 'execute'])->name('execute');
    Route::get('/history', [ApiTestController::class, 'history'])->name('history');
});

// マスタ関係 /master
Route::middleware(['auth', 'verified'])->prefix('master')->name('master.')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Master/Index');
    })->name('top');

    // ユーザーマスタ（API検索→登録）
    Route::get('users', [MasterUserController::class, 'index'])->name('users.index');
    Route::get('users/create', [MasterUserController::class, 'create'])->name('users.create');
    Route::post('users/search', [MasterUserController::class, 'searchApi'])->name('users.search');
    Route::post('users', [MasterUserController::class, 'store'])->name('users.store');
    Route::get('users/{id}', [MasterUserController::class, 'show'])->whereNumber('id')->name('users.show');
    Route::get('users/{id}/edit', [MasterUserController::class, 'edit'])->whereNumber('id')->name('users.edit');
    Route::put('users/{id}', [MasterUserController::class, 'update'])->whereNumber('id')->name('users.update');
    Route::delete('users/{id}', [MasterUserController::class, 'destroy'])->whereNumber('id')->name('users.destroy');

    // 部品マスタ（API検索→登録）
    Route::get('parts', [MasterPartController::class, 'index'])->name('parts.index');
    Route::get('parts/create', [MasterPartController::class, 'create'])->name('parts.create');
    Route::post('parts/search', [MasterPartController::class, 'searchApi'])->name('parts.search');
    Route::post('parts', [MasterPartController::class, 'store'])->name('parts.store');
    Route::get('parts/{id}', [MasterPartController::class, 'show'])->whereNumber('id')->name('parts.show');
    Route::post('parts/{id}/image', [MasterPartController::class, 'uploadImage'])->whereNumber('id')->name('parts.image.upload');
    Route::delete('parts/{id}/image', [MasterPartController::class, 'destroyImage'])->whereNumber('id')->name('parts.image.destroy');
    Route::put('parts/{id}/display-name', [MasterPartController::class, 'updateDisplayName'])->whereNumber('id')->name('parts.update-display-name');
    Route::put('parts/{id}/memo', [MasterPartController::class, 'updateMemo'])->whereNumber('id')->name('parts.update-memo');
    Route::post('parts/{id}/equipments', [MasterPartController::class, 'attachEquipment'])->whereNumber('id')->name('parts.equipments.attach');
    Route::delete('parts/{partId}/equipments/{equipmentId}', [MasterPartController::class, 'detachEquipment'])->whereNumber('partId')->whereNumber('equipmentId')->name('parts.equipments.detach');
    Route::delete('parts/{id}', [MasterPartController::class, 'destroy'])->whereNumber('id')->name('parts.destroy');

    // 設備マスタ
    Route::get('equipments', [MasterEquipmentController::class, 'index'])->name('equipments.index');
    Route::get('equipments/create', [MasterEquipmentController::class, 'create'])->name('equipments.create');
    Route::post('equipments', [MasterEquipmentController::class, 'store'])->name('equipments.store');
    Route::get('equipments/{id}', [MasterEquipmentController::class, 'show'])->whereNumber('id')->name('equipments.show');
    Route::post('equipments/{id}/image', [MasterEquipmentController::class, 'uploadImage'])->whereNumber('id')->name('equipments.image.upload');
    Route::delete('equipments/{id}/image', [MasterEquipmentController::class, 'destroyImage'])->whereNumber('id')->name('equipments.image.destroy');
    Route::get('equipments/{id}/edit', [MasterEquipmentController::class, 'edit'])->whereNumber('id')->name('equipments.edit');
    Route::put('equipments/{id}', [MasterEquipmentController::class, 'update'])->whereNumber('id')->name('equipments.update');
    Route::delete('equipments/{id}', [MasterEquipmentController::class, 'destroy'])->whereNumber('id')->name('equipments.destroy');

    $masterKeys = 'work-statuses|work-priorities|work-purposes|work-content-tags|repair-types|attachment-types|work-activity-types|work-cost-categories';
    Route::get('/{masterKey}', [MasterController::class, 'index'])->where('masterKey', $masterKeys)->name('index');
    Route::get('/{masterKey}/create', [MasterController::class, 'create'])->where('masterKey', $masterKeys)->name('create');
    Route::post('/{masterKey}', [MasterController::class, 'store'])->where('masterKey', $masterKeys)->name('store');
    Route::get('/{masterKey}/{id}', [MasterController::class, 'show'])->where('masterKey', $masterKeys)->whereNumber('id')->name('show');
    Route::get('/{masterKey}/{id}/edit', [MasterController::class, 'edit'])->where('masterKey', $masterKeys)->whereNumber('id')->name('edit');
    Route::put('/{masterKey}/{id}', [MasterController::class, 'update'])->where('masterKey', $masterKeys)->whereNumber('id')->name('update');
    Route::delete('/{masterKey}/{id}', [MasterController::class, 'destroy'])->where('masterKey', $masterKeys)->whereNumber('id')->name('destroy');
});

require __DIR__.'/auth.php';
