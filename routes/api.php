<?php

use App\Http\Controllers\API\V1\Auth\AuthController;
use App\Http\Controllers\API\V1\Event\CheckInController;
use App\Http\Controllers\API\V1\Event\EventController;
use App\Http\Controllers\API\V1\Event\TicketTypeController;
use App\Http\Controllers\API\V1\Organization\OrganizationController;
use App\Http\Controllers\API\V1\Organization\MemberController;
use App\Http\Controllers\API\V1\Public\PublicEventController;
use App\Http\Controllers\API\V1\Public\PublicRegistrationController;
use App\Http\Controllers\API\V1\Registration\RegistrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    // Health check endpoint
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Authentication routes
    Route::prefix('auth')->group(function () {
        // Public auth routes
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        // Protected auth routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // Public event discovery endpoints
    Route::prefix('public/events')->group(function () {
        Route::get('/', [PublicEventController::class, 'index']);
        Route::get('/{event:uuid}', [PublicEventController::class, 'show']);
        Route::get('/{event:uuid}/availability', [PublicEventController::class, 'availability']);

        // Public ticket types
        Route::get('/{event:uuid}/ticket-types', [TicketTypeController::class, 'index']);
        Route::get('/{event:uuid}/ticket-types/{ticketType}', [TicketTypeController::class, 'show']);

        // Public registration
        Route::post('/{event:uuid}/register', [PublicRegistrationController::class, 'register']);
    });

    // Public registration lookup
    Route::get('/public/registrations/{registration:uuid}', [PublicRegistrationController::class, 'show']);

    // Protected routes (Sanctum authentication required)
    Route::middleware('auth:sanctum')->group(function () {
        // Organization Management
        Route::apiResource('organizations', OrganizationController::class);

        // Team member management
        Route::prefix('organizations/{organization}')->group(function () {
            Route::get('/members', [MemberController::class, 'index']);
            Route::post('/members', [MemberController::class, 'store']);
            Route::put('/members/{member}', [MemberController::class, 'update']);
            Route::delete('/members/{member}', [MemberController::class, 'destroy']);

            // Organization events
            Route::get('/events', [EventController::class, 'index']);
        });

        // Event Management
        Route::prefix('events')->group(function () {
            Route::post('/', [EventController::class, 'store']);
            Route::put('/{event:uuid}', [EventController::class, 'update']);
            Route::delete('/{event:uuid}', [EventController::class, 'destroy']);

            // Ticket Type Management
            Route::post('/{event:uuid}/ticket-types', [TicketTypeController::class, 'store']);
            Route::put('/{event:uuid}/ticket-types/{ticketType}', [TicketTypeController::class, 'update']);
            Route::delete('/{event:uuid}/ticket-types/{ticketType}', [TicketTypeController::class, 'destroy']);

        });

        // User Registrations
        Route::prefix('registrations')->group(function () {
            Route::get('/', [RegistrationController::class, 'index']);
            Route::get('/{registration:uuid}', [RegistrationController::class, 'show']);
            Route::post('/{registration:uuid}/cancel', [RegistrationController::class, 'cancel']);
        });

        // Authenticated user registration for events
        Route::post('/events/{event:uuid}/register', [RegistrationController::class, 'store']);

        // Event Check-In
        Route::prefix('events/{event:uuid}')->group(function () {
            Route::post('/check-in', [CheckInController::class, 'checkIn']);
            Route::get('/check-in/stats', [CheckInController::class, 'statistics']);
            Route::get('/registrations', [CheckInController::class, 'registrations']);
        });
    });
});
