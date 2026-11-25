<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MechanicController;
use App\Http\Controllers\RiderController;
use Illuminate\Support\Facades\Route;

// Home
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route(auth()->user()->role.'.dashboard')
        : redirect()->route('login');
})->name('home');

// Authentication
Route::middleware('guest')->group(function () {

    Route::get('/login', [
        AuthController::class, 'showLogin'
    ])->name('login');

    Route::post('/login', [
        AuthController::class, 'login'
    ]);

    Route::get('/register', [
        AuthController::class, 'showRegister'
    ])->name('register');

    Route::post('/register', [
        AuthController::class, 'register'
    ]);
});

Route::post('/logout', [
    AuthController::class, 'logout'
])->middleware('auth')->name('logout');


// RIDER ROUTES
Route::middleware(['auth', 'role:rider'])
    ->prefix('rider')
    ->name('rider.')
    ->group(function () {

        Route::get('/dashboard', [
            RiderController::class, 'dashboard'
        ])->name('dashboard');

        Route::get('/vehicles', [
            RiderController::class, 'vehicles'
        ])->name('vehicles.index');

        Route::post('/vehicles', [
            RiderController::class, 'storeVehicle'
        ])->name('vehicles.store');

        Route::get('/history', [
            RiderController::class, 'history'
        ])->name('history');

        Route::get('/request-help', [
            RiderController::class, 'requestHelp'
        ])->name('request-help');

        Route::post('/requests', [
            RiderController::class, 'storeRequest'
        ])->name('requests.store');

        Route::get('/requests/{serviceRequest}', [
            RiderController::class, 'showRequest'
        ])->name('requests.show');

        // Rider confirms arrival, repair and completion
        Route::post('/requests/{serviceRequest}/status', [
            RiderController::class, 'updateStatus'
        ])->name('requests.status');
    });


// MECHANIC ROUTES
Route::middleware(['auth', 'role:mechanic'])
    ->prefix('mechanic')
    ->name('mechanic.')
    ->group(function () {

        Route::get('/dashboard', [
            MechanicController::class, 'dashboard'
        ])->name('dashboard');

        Route::get('/shop-requests', [
            MechanicController::class, 'shopRequests'
        ])->name('shop-requests');

        Route::get('/team', [
            MechanicController::class, 'team'
        ])->name('team');

        Route::get('/shop-profile', [
            MechanicController::class, 'shopProfile'
        ])->name('shop-profile');

        Route::post('/availability', [
            MechanicController::class, 'toggleAvailability'
        ])->name('availability');

        Route::post('/shop-profile', [
            MechanicController::class, 'updateShopProfile'
        ])->name('shop-profile.update');

        Route::post('/team', [
            MechanicController::class, 'storeTeamMember'
        ])->name('team.store');

        Route::post('/team/{member}/toggle', [
            MechanicController::class, 'toggleTeamMember'
        ])->name('team.toggle');

        Route::post('/offers/{attempt}/accept', [
            MechanicController::class, 'acceptOffer'
        ])->name('offers.accept');

        Route::post('/offers/{attempt}/decline', [
            MechanicController::class, 'declineOffer'
        ])->name('offers.decline');

        Route::post('/requests/{serviceRequest}/assign', [
            MechanicController::class, 'assignMechanic'
        ])->name('requests.assign');

        // Mechanic can only mark On the Way
        Route::post('/requests/{serviceRequest}/status', [
            MechanicController::class, 'updateStatus'
        ])->name('requests.status');
    });


// ADMIN ROUTES
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', [
            AdminController::class, 'dashboard'
        ])->name('dashboard');

        Route::get('/dashboard/geography', [
            AdminController::class, 'dashboardGeography'
        ])->name('dashboard.geography');

        Route::get('/live-dispatch', [
            AdminController::class, 'liveDispatch'
        ])->name('live-dispatch');

        Route::get('/users-mechanics', [
            AdminController::class, 'usersMechanics'
        ])->name('users-mechanics');

        Route::get('/verification', [
            AdminController::class, 'verification'
        ])->name('verification');

        Route::get('/monitoring', [
            AdminController::class, 'monitoring'
        ])->name('monitoring');

        Route::get('/audit-trail', [
            AdminController::class, 'auditTrail'
        ])->name('audit-trail');

        Route::get('/settings', [
            AdminController::class, 'settings'
        ])->name('settings');

        Route::post('/settings', [
            AdminController::class, 'updateSettings'
        ])->name('settings.update');

        Route::post('/system-status', [
            AdminController::class, 'updateSystemStatus'
        ])->name('system-status.update');

        Route::post('/mechanics/{user}/verify', [
            AdminController::class, 'verifyMechanic'
        ])->name('mechanics.verify');

        Route::post('/users/{user}/suspend', [
            AdminController::class, 'toggleSuspension'
        ])->name('users.suspend');
    });
