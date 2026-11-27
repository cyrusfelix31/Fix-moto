<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MechanicController;
use App\Http\Controllers\RiderController;
use App\Http\Controllers\ShopProfileController;
use App\Http\Controllers\ShopVerificationController;
use App\Http\Middleware\EnsureMechanicVerified;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {

    return auth()->check()

        ? redirect()->route(
            auth()->user()->role.'.dashboard'
        )

        : redirect()->route('login');

})->name('home');

// AUTHENTICATION

Route::middleware('guest')->group(function () {

    Route::get('/login', [
        AuthController::class, 'showLogin',
    ])->name('login');

    Route::post('/login', [
        AuthController::class, 'login',
    ]);

    Route::get('/register', [
        AuthController::class, 'showRegister',
    ])->name('register');

    Route::post('/register', [
        AuthController::class, 'register',
    ]);

});

Route::post('/logout', [
    AuthController::class, 'logout',
])->middleware('auth')->name('logout');

// LOCATION SEARCH AND GPS

Route::middleware('throttle:60,1')->group(function () {

    Route::get('/locations/search', [
        LocationController::class, 'search',
    ])->name('locations.search');

    Route::get('/locations/reverse', [
        LocationController::class, 'reverse',
    ])->name('locations.reverse');

});

// MECHANIC VERIFICATION STATUS

Route::middleware(['auth', 'role:mechanic'])

    ->prefix('mechanic')

    ->name('mechanic.')

    ->group(function () {

        Route::get('/verification-status', [

            ShopVerificationController::class,
            'status',

        ])->name('verification.status');

        Route::post('/verification-resubmit', [

            ShopVerificationController::class,
            'resubmit',

        ])->middleware('throttle:5,1')
            ->name('verification.resubmit');

    });

// RIDER ROUTES

Route::middleware(['auth', 'role:rider'])

    ->prefix('rider')

    ->name('rider.')

    ->group(function () {

        Route::get('/dashboard', [
            RiderController::class, 'dashboard',
        ])->name('dashboard');

        Route::get('/vehicles', [
            RiderController::class, 'vehicles',
        ])->name('vehicles.index');

        Route::post('/vehicles', [
            RiderController::class, 'storeVehicle',
        ])->name('vehicles.store');

        Route::get('/history', [
            RiderController::class, 'history',
        ])->name('history');

        Route::get('/request-help', [
            RiderController::class, 'requestHelp',
        ])->name('request-help');

        Route::post('/requests', [
            RiderController::class, 'storeRequest',
        ])->name('requests.store');

        Route::get('/requests/{serviceRequest}', [
            RiderController::class, 'showRequest',
        ])->name('requests.show');

        Route::post('/requests/{serviceRequest}/status', [
            RiderController::class, 'updateStatus',
        ])->name('requests.status');

    });

// VERIFIED MECHANIC ROUTES

Route::middleware([
    'auth',
    'role:mechanic',
    EnsureMechanicVerified::class,
])

    ->prefix('mechanic')

    ->name('mechanic.')

    ->group(function () {

        Route::get('/dashboard', [
            MechanicController::class, 'dashboard',
        ])->name('dashboard');

        Route::get('/shop-requests', [
            MechanicController::class, 'shopRequests',
        ])->name('shop-requests');

        Route::get('/team', [
            MechanicController::class, 'team',
        ])->name('team');

        Route::get('/shop-profile', [
            MechanicController::class, 'shopProfile',
        ])->name('shop-profile');

        Route::post('/availability', [
            MechanicController::class, 'toggleAvailability',
        ])->name('availability');

        Route::post('/shop-profile', [
            MechanicController::class, 'updateShopProfile',
        ])->name('shop-profile.update');

        Route::post('/shop-profile/owner', [
            MechanicController::class, 'updateOwnerProfile',
        ])->name('shop-profile.owner.update');

        Route::post('/shop-profile/photo', [
            MechanicController::class, 'updateProfilePhoto',
        ])->name('shop-profile.photo');

        // Retain the dedicated profile controller endpoints for secure photo
        // delivery and compatibility with existing profile clients.
        Route::get('/shop-profile/owner/photo', [
            ShopProfileController::class, 'ownerPhoto',
        ])->name('shop-profile.owner.photo');

        Route::post('/shop-profile/owner/details', [
            ShopProfileController::class, 'updateOwner',
        ])->name('shop-profile.owner.details.update');

        Route::post('/shop-profile/members', [
            ShopProfileController::class, 'storeMember',
        ])->name('shop-profile.members.store');

        Route::post('/shop-profile/members/{member}', [
            ShopProfileController::class, 'updateMember',
        ])->name('shop-profile.members.update');

        Route::get('/shop-profile/members/{member}/photo', [
            ShopProfileController::class, 'memberPhoto',
        ])->name('shop-profile.members.photo');

        Route::get('/shop-profile/details', [
            ShopProfileController::class, 'index',
        ])->name('shop-profile.details');

        Route::post('/shop-profile/details', [
            ShopProfileController::class, 'update',
        ])->name('shop-profile.details.update');

        Route::post('/team', [
            MechanicController::class, 'storeTeamMember',
        ])->name('team.store');

        Route::post('/team/{member}/toggle', [
            MechanicController::class, 'toggleTeamMember',
        ])->name('team.toggle');

        Route::put('/team/{member}', [
            MechanicController::class, 'updateTeamMember',
        ])->name('team.update');

        Route::delete('/team/{member}', [
            MechanicController::class, 'removeTeamMember',
        ])->name('team.destroy');

        Route::post('/shop-services', [
            MechanicController::class, 'storeShopService',
        ])->name('shop-services.store');

        Route::put('/shop-services/{service}', [
            MechanicController::class, 'updateShopService',
        ])->name('shop-services.update');

        Route::post('/shop-services/{service}/toggle', [
            MechanicController::class, 'toggleShopService',
        ])->name('shop-services.toggle');

        Route::delete('/shop-services/{service}', [
            MechanicController::class, 'removeShopService',
        ])->name('shop-services.destroy');

        Route::post('/offers/{attempt}/accept', [
            MechanicController::class, 'acceptOffer',
        ])->name('offers.accept');

        Route::post('/offers/{attempt}/decline', [
            MechanicController::class, 'declineOffer',
        ])->name('offers.decline');

        Route::post('/requests/{serviceRequest}/assign', [
            MechanicController::class, 'assignMechanic',
        ])->name('requests.assign');

        Route::post('/requests/{serviceRequest}/status', [
            MechanicController::class, 'updateStatus',
        ])->name('requests.status');

    });

// ADMIN ROUTES

Route::middleware(['auth', 'role:admin'])

    ->prefix('admin')

    ->name('admin.')

    ->group(function () {

        Route::get('/dashboard', [
            AdminController::class, 'dashboard',
        ])->name('dashboard');

        Route::get('/dashboard/geography', [
            AdminController::class, 'dashboardGeography',
        ])->name('dashboard.geography');

        Route::get('/live-dispatch', [
            AdminController::class, 'liveDispatch',
        ])->name('live-dispatch');

        Route::get('/users-mechanics', [
            AdminController::class, 'usersMechanics',
        ])->name('users-mechanics');

        Route::get('/monitoring', [
            AdminController::class, 'monitoring',
        ])->name('monitoring');

        Route::get('/audit-trail', [
            AdminController::class, 'auditTrail',
        ])->name('audit-trail');

        Route::get('/settings', [
            AdminController::class, 'settings',
        ])->name('settings');

        Route::post('/settings', [
            AdminController::class, 'updateSettings',
        ])->name('settings.update');

        Route::post('/system-status', [
            AdminController::class, 'updateSystemStatus',
        ])->name('system-status.update');

        Route::post('/users/{user}/suspend', [
            AdminController::class, 'toggleSuspension',
        ])->name('users.suspend');

        // VERIFICATION MANAGEMENT

        Route::get('/verification', [

            ShopVerificationController::class,
            'index',

        ])->name('verification');

        Route::get('/verification/{user}', [

            ShopVerificationController::class,
            'show',

        ])->name('verification.show');

        Route::get('/documents/{document}', [

            ShopVerificationController::class,
            'viewDocument',

        ])->name('documents.view');

        Route::post('/documents/{document}/review', [

            ShopVerificationController::class,
            'reviewDocument',

        ])->name('documents.review');

        Route::post('/mechanics/{user}/verify', [

            ShopVerificationController::class,
            'decide',

        ])->name('mechanics.verify');

    });
