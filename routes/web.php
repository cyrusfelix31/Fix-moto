<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MechanicController;
use App\Http\Controllers\RiderController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ShopVerificationController;
use App\Http\Controllers\ShopProfileController;
use App\Http\Middleware\EnsureMechanicVerified;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return auth()->check()
        ? redirect()->route(
            auth()->user()->role.'.dashboard'
        )
        : redirect()->route('login');
})->name('home');


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| LOCATION SEARCH
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:60,1')->group(function () {

    Route::get('/locations/search', [
        LocationController::class, 'search'
    ])->name('locations.search');

    Route::get('/locations/reverse', [
        LocationController::class, 'reverse'
    ])->name('locations.reverse');

});


/*
|--------------------------------------------------------------------------
| MECHANIC VERIFICATION
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:mechanic'])
    ->prefix('mechanic')
    ->name('mechanic.')
    ->group(function () {

        Route::get('/verification-status', [
            ShopVerificationController::class, 'status'
        ])->name('verification.status');

        Route::post('/verification-resubmit', [
            ShopVerificationController::class, 'resubmit'
        ])->middleware('throttle:5,1')
          ->name('verification.resubmit');

    });


/*
|--------------------------------------------------------------------------
| RIDER ROUTES
|--------------------------------------------------------------------------
*/

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

        Route::post('/requests/{serviceRequest}/status', [
            RiderController::class, 'updateStatus'
        ])->name('requests.status');

    });


/*
|--------------------------------------------------------------------------
| VERIFIED MECHANIC ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'role:mechanic',
    EnsureMechanicVerified::class,
])
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

        Route::post('/availability', [
            MechanicController::class, 'toggleAvailability'
        ])->name('availability');


        /*
        |--------------------------------------------------------------------------
        | NEW SHOP PROFILE ROUTES
        |--------------------------------------------------------------------------
        */

        Route::get('/shop-profile', [
            ShopProfileController::class, 'index'
        ])->name('shop-profile');

        Route::post('/shop-profile', [
            ShopProfileController::class, 'update'
        ])->name('shop-profile.update');

        Route::post('/shop-profile/owner', [
            ShopProfileController::class, 'updateOwner'
        ])->name('shop-profile.owner.update');

        Route::get('/shop-profile/owner/photo', [
            ShopProfileController::class, 'ownerPhoto'
        ])->name('shop-profile.owner.photo');

        Route::post('/shop-profile/members', [
            ShopProfileController::class, 'storeMember'
        ])->name('shop-profile.members.store');

        Route::post('/shop-profile/members/{member}', [
            ShopProfileController::class, 'updateMember'
        ])->name('shop-profile.members.update');

        Route::get('/shop-profile/members/{member}/photo', [
            ShopProfileController::class, 'memberPhoto'
        ])->name('shop-profile.members.photo');


        /*
        |--------------------------------------------------------------------------
        | EXISTING MECHANIC TEAM AND REQUEST ROUTES
        |--------------------------------------------------------------------------
        */

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

        Route::post('/requests/{serviceRequest}/status', [
            MechanicController::class, 'updateStatus'
        ])->name('requests.status');

    });


/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/

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

        Route::post('/users/{user}/suspend', [
            AdminController::class, 'toggleSuspension'
        ])->name('users.suspend');

        Route::get('/verification', [
            ShopVerificationController::class, 'index'
        ])->name('verification');

        Route::get('/verification/{user}', [
            ShopVerificationController::class, 'show'
        ])->name('verification.show');

        Route::get('/documents/{document}', [
            ShopVerificationController::class, 'viewDocument'
        ])->name('documents.view');

        Route::post('/documents/{document}/review', [
            ShopVerificationController::class, 'reviewDocument'
        ])->name('documents.review');

        Route::post('/mechanics/{user}/verify', [
            ShopVerificationController::class, 'decide'
        ])->name('mechanics.verify');

    });