<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Route;
use App\Models\User;
use App\Models\Driver;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // Check if user is admin
    private function checkAdmin(Request $request)
    {
        if ($request->user()->user_type !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }
        return null;
    }

    // ==================== DASHBOARD ====================

    public function getDashboardStats(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $totalPassengers   = User::where('user_type', 'passenger')->count();
        $totalDrivers      = User::where('user_type', 'driver')->count();
        $activeDrivers     = Driver::where('status', 'available')->count();
        $totalBookings     = Booking::count();
        $pendingBookings   = Booking::where('status', 'pending')->count();
        $completedBookings = Booking::where('status', 'completed')->count();
        $cancelledBookings = Booking::where('status', 'cancelled')->count();
        $totalRevenue      = Booking::where('status', 'completed')->sum('final_price');

        return response()->json([
            'stats' => [
                'users' => [
                    'passengers'     => $totalPassengers,
                    'drivers'        => $totalDrivers,
                    'active_drivers' => $activeDrivers,
                ],
                'bookings' => [
                    'total'     => $totalBookings,
                    'pending'   => $pendingBookings,
                    'completed' => $completedBookings,
                    'cancelled' => $cancelledBookings,
                ],
                'revenue' => [
                    'total' => $totalRevenue,
                ],
            ]
        ], 200);
    }

    // ==================== LOCATION MANAGEMENT ====================

    public function getLocations(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;
        $locations = Location::orderBy('name')->get();
        return response()->json(['locations' => $locations], 200);
    }

    public function createLocation(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:locations,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $location = Location::create([
            'name'      => $request->name,
            'is_active' => true,
        ]);

        return response()->json([
            'message'  => 'Location created',
            'location' => $location
        ], 201);
    }

    public function updateLocation(Request $request, $id)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|unique:locations,name,' . $id,
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $location = Location::find($id);
        if (!$location) return response()->json(['message' => 'Location not found'], 404);

        $location->update([
            'name'      => $request->name,
            'is_active' => $request->is_active,
        ]);

        return response()->json([
            'message'  => 'Location updated',
            'location' => $location
        ], 200);
    }

    public function deleteLocation(Request $request, $id)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $location = Location::find($id);
        if (!$location) return response()->json(['message' => 'Location not found'], 404);

        $location->delete();
        return response()->json(['message' => 'Location deleted'], 200);
    }

    // ==================== ROUTE MANAGEMENT ====================

    public function getRoutes(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;
        $routes = Route::orderBy('pickup_location')->get();
        return response()->json(['routes' => $routes], 200);
    }

    public function createRoute(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validator = Validator::make($request->all(), [
            'pickup_location'  => 'required|string',
            'dropoff_location' => 'required|string',
            'price_4_seater'   => 'required|numeric|min:0',
            'price_7_seater'   => 'required|numeric|min:0',
            'price_8_seater'   => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existing = Route::where('pickup_location', $request->pickup_location)
            ->where('dropoff_location', $request->dropoff_location)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Route already exists'], 400);
        }

        $route = Route::create([
            'pickup_location'  => $request->pickup_location,
            'dropoff_location' => $request->dropoff_location,
            'price_4_seater'   => $request->price_4_seater,
            'price_7_seater'   => $request->price_7_seater,
            'price_8_seater'   => $request->price_8_seater,
            'is_active'        => true,
            'created_by'       => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Route created',
            'route'   => $route
        ], 201);
    }

    public function updateRoute(Request $request, $id)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validator = Validator::make($request->all(), [
            'pickup_location'  => 'required|string',
            'dropoff_location' => 'required|string',
            'price_4_seater'   => 'required|numeric|min:0',
            'price_7_seater'   => 'required|numeric|min:0',
            'price_8_seater'   => 'required|numeric|min:0',
            'is_active'        => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $route = Route::find($id);
        if (!$route) return response()->json(['message' => 'Route not found'], 404);

        $route->update([
            'pickup_location'  => $request->pickup_location,
            'dropoff_location' => $request->dropoff_location,
            'price_4_seater'   => $request->price_4_seater,
            'price_7_seater'   => $request->price_7_seater,
            'price_8_seater'   => $request->price_8_seater,
            'is_active'        => $request->is_active,
        ]);

        return response()->json([
            'message' => 'Route updated',
            'route'   => $route
        ], 200);
    }

    public function deleteRoute(Request $request, $id)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $route = Route::find($id);
        if (!$route) return response()->json(['message' => 'Route not found'], 404);

        $route->delete();
        return response()->json(['message' => 'Route deleted'], 200);
    }

    // ==================== DRIVER MANAGEMENT ====================

    public function getDrivers(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $drivers = User::where('user_type', 'driver')
            ->with('driver')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'email'          => $user->email,
                    'phone'          => $user->phone,
                    'created_at'     => $user->created_at,
                    'profile_photo'  => $user->profile_photo
                        ? asset('storage/' . $user->profile_photo)
                        : null,
                    'vehicle_type'   => $user->driver?->vehicle_type,
                    'vehicle_number' => $user->driver?->vehicle_number,
                    'license_number' => $user->driver?->license_number,
                    'status'         => $user->driver?->status ?? 'offline',
                    'is_available'   => $user->driver?->is_available ?? false,
                    'driver_id'      => $user->driver?->id,
                ];
            });

        return response()->json(['drivers' => $drivers], 200);
    }

    public function addDriver(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validator = Validator::make($request->all(), [
            'name'           => 'required|string',
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|min:6',
            'phone'          => 'required|string',
            'vehicle_type'   => 'required|in:4-seater,7-seater,8-seater',
            'vehicle_number' => 'required|string',
            'license_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'phone'     => $request->phone,
            'user_type' => 'driver',
        ]);

        $driver = Driver::create([
            'user_id'        => $user->id,
            'vehicle_type'   => $request->vehicle_type,
            'vehicle_number' => $request->vehicle_number,
            'license_number' => $request->license_number,
            'status'         => 'offline',
            'is_available'   => false,
        ]);

        return response()->json([
            'message' => 'Driver added successfully',
            'user'    => $user,
            'driver'  => $driver,
        ], 201);
    }

    public function deleteDriver(Request $request, $id)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $user = User::where('id', $id)->where('user_type', 'driver')->first();
        if (!$user) return response()->json(['message' => 'Driver not found'], 404);

        // ✅ Get driver id before deleting
        $driver   = Driver::where('user_id', $id)->first();
        $driverId = $driver?->id;

        Booking::where('driver_id', $id)->delete();
        Driver::where('user_id', $id)->delete();
        $user->delete();

        // ✅ Delete from Firebase Realtime Database
        if ($driverId) {
            try {
                $firebaseUrl = env('FIREBASE_DATABASE_URL', 'https://taxiapp-e5f40-default-rtdb.asia-southeast1.firebasedatabase.app');
                $ch = curl_init("{$firebaseUrl}/drivers/{$driverId}.json");
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            } catch (\Exception $e) {
                \Log::error('Firebase delete error: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Driver deleted successfully'], 200);
    }

    public function toggleDriverStatus(Request $request, $userId)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $driver = Driver::where('user_id', $userId)->first();
        if (!$driver) return response()->json(['message' => 'Driver not found'], 404);

        $driver->is_available = !$driver->is_available;
        $driver->save();

        return response()->json([
            'message'      => 'Driver status updated',
            'is_available' => $driver->is_available,
        ], 200);
    }

    public function updateDriver(Request $request, $id)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $user = User::where('id', $id)->where('user_type', 'driver')->first();
        if (!$user) return response()->json(['message' => 'Driver not found'], 404);

        $user->name  = $request->name ?? $user->name;
        $user->phone = $request->phone ?? $user->phone;
        $user->save();

        $driver = Driver::where('user_id', $id)->first();
        if ($driver) {
            $driver->vehicle_type   = $request->vehicle_type ?? $driver->vehicle_type;
            $driver->vehicle_number = $request->vehicle_number ?? $driver->vehicle_number;
            $driver->license_number = $request->license_number ?? $driver->license_number;
            $driver->save();
        }

        return response()->json(['message' => 'Driver updated successfully'], 200);
    }

    // ==================== PASSENGER MANAGEMENT ====================

    public function getPassengers(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $passengers = User::where('user_type', 'passenger')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id'            => $user->id,
                    'name'          => $user->name,
                    'email'         => $user->email,
                    'phone'         => $user->phone,
                    'created_at'    => $user->created_at,
                    'profile_photo' => $user->profile_photo
                        ? asset('storage/' . $user->profile_photo)
                        : null,
                    'total_rides'   => Booking::where('passenger_id', $user->id)
                        ->where('status', 'completed')
                        ->count(),
                ];
            });

        return response()->json(['passengers' => $passengers], 200);
    }

    public function addPassenger(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'phone'    => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'phone'     => $request->phone,
            'user_type' => 'passenger',
        ]);

        return response()->json([
            'message' => 'Passenger added successfully',
            'user'    => $user,
        ], 201);
    }

    public function deletePassenger(Request $request, $id)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $user = User::where('id', $id)->where('user_type', 'passenger')->first();
        if (!$user) return response()->json(['message' => 'Passenger not found'], 404);

        Booking::where('passenger_id', $id)->delete();
        $user->delete();

        return response()->json(['message' => 'Passenger deleted successfully'], 200);
    }

    public function updatePassenger(Request $request, $id)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $user = User::where('id', $id)->where('user_type', 'passenger')->first();
        if (!$user) return response()->json(['message' => 'Passenger not found'], 404);

        $user->name  = $request->name ?? $user->name;
        $user->phone = $request->phone ?? $user->phone;
        $user->save();

        return response()->json(['message' => 'Passenger updated successfully'], 200);
    }

    // ==================== BOOKING MANAGEMENT ====================

    public function getAllBookings(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $bookings = Booking::with(['passenger', 'driver'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['bookings' => $bookings], 200);
    }

    // ==================== USER MANAGEMENT ====================

    public function getUsers(Request $request)
    {
        if ($error = $this->checkAdmin($request)) return $error;

        $users = User::with('driver')->orderBy('created_at', 'desc')->get();
        return response()->json(['users' => $users], 200);
    }
}