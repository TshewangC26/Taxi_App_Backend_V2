<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BookingController; 
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\DriverController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\RouteController;

// Public routes (no authentication required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/get-username', [AuthController::class, 'getUsernameByEmail']);

// Protected routes (require authentication token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'getProfile']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Get current user info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Booking routes
    Route::post('/bookings', [BookingController::class, 'createBooking']);
    Route::get('/bookings/passenger', [BookingController::class, 'getPassengerBookings']);
    Route::get('/bookings/available', [BookingController::class, 'getAvailableBookings']);
    Route::post('/bookings/{id}/accept', [BookingController::class, 'acceptBooking']);
    Route::post('/bookings/{id}/start', [BookingController::class, 'startRide']);
    Route::post('/bookings/{id}/complete', [BookingController::class, 'completeRide']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancelBooking']);
    Route::delete('/bookings/{id}', [BookingController::class, 'deleteBooking']);
    Route::post('/bookings/{id}/rate', [BookingController::class, 'rateDriver']);

    // Payment routes
    Route::post('/payments', [PaymentController::class, 'createPayment']);
    Route::get('/payments/booking/{bookingId}', [PaymentController::class, 'getPaymentByBooking']);
    Route::get('/payments/my-payments', [PaymentController::class, 'getUserPayments']);
    Route::post('/payments/{id}/status', [PaymentController::class, 'updatePaymentStatus']);
    Route::get('/payments/info', [PaymentController::class, 'getPaymentInfo']);

    // Driver Profile routes
    Route::get('/driver/profile', [DriverController::class, 'getProfile']);
    Route::post('/driver/profile/update', [DriverController::class, 'updateProfile']); // ← ADDED
    Route::post('/driver/toggle-availability', [DriverController::class, 'toggleAvailability']);
    Route::put('/driver/vehicle-info', [DriverController::class, 'updateVehicleInfo']);
    Route::put('/driver/bank-details', [DriverController::class, 'updateBankDetails']);
    Route::post('/driver/upload-qr', [DriverController::class, 'uploadQRCode']);
    Route::get('/driver/earnings', [DriverController::class, 'getEarnings']);
    Route::get('/driver/my-rides', [DriverController::class, 'getMyRides']);
    Route::delete('/driver/my-rides/{id}', [DriverController::class, 'deleteMyRide']);
    Route::get('/driver/payment-details/{bookingId}', [DriverController::class, 'getDriverPaymentDetails']);
    Route::get('/drivers/status', [DriverController::class, 'getAllDriversStatus']);
    Route::post('/driver/update-location', [DriverController::class, 'updateLocation']);
    Route::get('/drivers/nearby', [DriverController::class, 'getNearbyDrivers']);

    // Admin routes
    Route::get('/admin/dashboard/stats', [AdminController::class, 'getDashboardStats']);

    // Location management
    Route::get('/admin/locations', [AdminController::class, 'getLocations']);
    Route::post('/admin/locations', [AdminController::class, 'createLocation']);
    Route::put('/admin/locations/{id}', [AdminController::class, 'updateLocation']);
    Route::delete('/admin/locations/{id}', [AdminController::class, 'deleteLocation']);

    // Route management
    Route::get('/admin/routes', [AdminController::class, 'getRoutes']);
    Route::post('/admin/routes', [AdminController::class, 'createRoute']);
    Route::put('/admin/routes/{id}', [AdminController::class, 'updateRoute']);
    Route::delete('/admin/routes/{id}', [AdminController::class, 'deleteRoute']);

    // Driver management
    Route::get('/admin/drivers', [AdminController::class, 'getDrivers']);
    Route::post('/admin/drivers', [AdminController::class, 'addDriver']);
    Route::delete('/admin/drivers/{id}', [AdminController::class, 'deleteDriver']);
    Route::put('/admin/drivers/{id}', [AdminController::class, 'updateDriver']);
    Route::put('/admin/passengers/{id}', [AdminController::class, 'updatePassenger']);
    Route::post('/admin/drivers/{userId}/toggle', [AdminController::class, 'toggleDriverStatus']);

    // Passenger management
    Route::get('/admin/passengers', [AdminController::class, 'getPassengers']);
    Route::post('/admin/passengers', [AdminController::class, 'addPassenger']);
    Route::delete('/admin/passengers/{id}', [AdminController::class, 'deletePassenger']);

    // Booking management
    Route::get('/admin/bookings', [AdminController::class, 'getAllBookings']);
    Route::get('/admin/users', [AdminController::class, 'getUsers']);

    // Public route listing
    Route::get('/routes', [RouteController::class, 'index']);
    Route::get('/routes/{id}', [RouteController::class, 'show']);
    Route::post('/routes', [RouteController::class, 'store']);
    Route::put('/routes/{id}', [RouteController::class, 'update']);
    Route::delete('/routes/{id}', [RouteController::class, 'destroy']);
});