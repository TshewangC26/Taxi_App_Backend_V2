<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class DriverController extends Controller
{
    // ✅ Helper to get correct URL (Cloudinary or local)
    private function getQrUrl($qrCodeImage): ?string
    {
        if (!$qrCodeImage) return null;
        if (str_starts_with($qrCodeImage, 'http')) {
            return $qrCodeImage;
        }
        return asset('storage/' . $qrCodeImage);
    }

    // Toggle driver availability (online/offline)
    public function toggleAvailability(Request $request)
    {
        $user = $request->user();

        if ($user->user_type !== 'driver') {
            return response()->json(['message' => 'Only drivers can toggle availability'], 403);
        }

        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found'], 404);
        }

        if ($driver->status === Driver::STATUS_OFFLINE) {
            $driver->setAvailable();
        } else {
            $driver->setOffline();
        }

        return response()->json([
            'message'      => 'Availability updated',
            'status'       => $driver->status,
            'is_available' => $driver->is_available,
        ], 200);
    }

    // Get driver profile with stats
    public function getProfile(Request $request)
    {
        $user = $request->user();

        if ($user->user_type !== 'driver') {
            return response()->json(['message' => 'Only drivers can access this'], 403);
        }

        $driver = Driver::where('user_id', $user->id)->with('user')->first();

        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found'], 404);
        }

        $totalRides    = $driver->bookings()->where('status', 'completed')->count();
        $totalEarnings = $driver->bookings()->where('status', 'completed')->sum('final_price');

        return response()->json([
            'driver' => [
                'id'                    => $driver->id,
                'name'                  => $driver->user->name ?? 'Unknown',
                'user_id'               => $driver->user_id,
                'vehicle_type'          => $driver->vehicle_type,
                'vehicle_number'        => $driver->vehicle_number,
                'license_number'        => $driver->license_number,
                'is_available'          => $driver->is_available,
                'status'                => $driver->status,
                'latitude'              => $driver->latitude,
                'longitude'             => $driver->longitude,
                'location_updated_at'   => $driver->location_updated_at,
                'bank_name'             => $driver->bank_name,
                'account_holder_name'   => $driver->account_holder_name,
                'account_number'        => $driver->account_number,
                'mobile_payment_number' => $driver->mobile_payment_number,
                // ✅ Fixed: handle Cloudinary URL
                'qr_code_image'         => $this->getQrUrl($driver->qr_code_image),
            ],
            'stats' => [
                'total_rides'    => $totalRides,
                'total_earnings' => $totalEarnings,
            ]
        ], 200);
    }

    // Update driver profile
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if ($user->user_type !== 'driver') {
            return response()->json(['message' => 'Only drivers can update their profile'], 403);
        }

        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'           => 'nullable|string|max:255',
            'email'          => 'nullable|email|unique:users,email,' . $user->id,
            'phone'          => 'nullable|string|max:20',
            'vehicle_type'   => 'nullable|in:4-seater,7-seater,8-seater',
            'vehicle_number' => 'nullable|string|max:50',
            'license_number' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('name'))  $user->name  = $request->name;
        if ($request->has('email')) $user->email = $request->email;
        if ($request->has('phone')) $user->phone = $request->phone;
        $user->save();

        $driverFields = ['vehicle_type', 'vehicle_number', 'license_number'];
        $driverData   = $request->only($driverFields);
        if (!empty($driverData)) {
            $driver->update($driverData);
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'driver'  => [
                'id'             => $driver->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone,
                'vehicle_type'   => $driver->vehicle_type,
                'vehicle_number' => $driver->vehicle_number,
                'license_number' => $driver->license_number,
            ],
        ], 200);
    }

    // Update driver vehicle information
    public function updateVehicleInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_type'   => 'required|in:4-seater,7-seater,8-seater',
            'vehicle_number' => 'required|string',
            'license_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if ($user->user_type !== 'driver') {
            return response()->json(['message' => 'Only drivers can update vehicle info'], 403);
        }

        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found'], 404);
        }

        $driver->update([
            'vehicle_type'   => $request->vehicle_type,
            'vehicle_number' => $request->vehicle_number,
            'license_number' => $request->license_number,
        ]);

        return response()->json([
            'message' => 'Vehicle information updated',
            'driver'  => $driver
        ], 200);
    }

    // Update bank details
    public function updateBankDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_name'             => 'nullable|string',
            'account_holder_name'   => 'nullable|string',
            'account_number'        => 'nullable|string',
            'mobile_payment_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if ($user->user_type !== 'driver') {
            return response()->json(['message' => 'Only drivers can update bank details'], 403);
        }

        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found'], 404);
        }

        $driver->update([
            'bank_name'             => $request->bank_name,
            'account_holder_name'   => $request->account_holder_name,
            'account_number'        => $request->account_number,
            'mobile_payment_number' => $request->mobile_payment_number,
        ]);

        return response()->json([
            'message' => 'Payment details updated',
            'driver'  => $driver
        ], 200);
    }

    // ✅ Upload QR code - now accepts Cloudinary URL
    public function uploadQRCode(Request $request)
    {
        $user = $request->user();

        if ($user->user_type !== 'driver') {
            return response()->json(['message' => 'Only drivers can upload QR code'], 403);
        }

        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found'], 404);
        }

        // ✅ Handle Cloudinary URL (new way)
        if ($request->qr_code_url) {
            $driver->update(['qr_code_image' => $request->qr_code_url]);
            return response()->json([
                'message'     => 'QR code uploaded successfully',
                'qr_code_url' => $request->qr_code_url,
            ], 200);
        }

        // Handle file upload (old way fallback)
        $validator = Validator::make($request->all(), [
            'qr_code' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($driver->qr_code_image && !str_starts_with($driver->qr_code_image, 'http')) {
            Storage::disk('public')->delete($driver->qr_code_image);
        }

        $path = $request->file('qr_code')->storePublicly('qr_codes', 'public');
        $driver->update(['qr_code_image' => $path]);

        return response()->json([
            'message'     => 'QR code uploaded successfully',
            'qr_code_url' => asset('storage/' . $path)
        ], 200);
    }

    // Get driver payment details by booking ID (for passenger)
    public function getDriverPaymentDetails($bookingId)
    {
        $booking = Booking::find($bookingId);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $driver = Driver::where('user_id', $booking->driver_id)->first();

        if (!$driver) {
            return response()->json(['message' => 'Driver not found'], 404);
        }

        return response()->json([
            'payment_details' => [
                'bank_name'             => $driver->bank_name,
                'account_holder_name'   => $driver->account_holder_name,
                'account_number'        => $driver->account_number,
                'mobile_payment_number' => $driver->mobile_payment_number,
                // ✅ Fixed: handle Cloudinary URL
                'qr_code_image'         => $this->getQrUrl($driver->qr_code_image),
            ]
        ], 200);
    }

    // Get driver earnings summary
    public function getEarnings(Request $request)
    {
        $user = $request->user();

        if ($user->user_type !== 'driver') {
            return response()->json(['message' => 'Only drivers can access earnings'], 403);
        }

        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json(['message' => 'Driver profile not found'], 404);
        }

        $completedBookings = $driver->bookings()->where('status', 'completed')->get();

        $totalEarnings = $completedBookings->sum('final_price');
        $totalRides    = $completedBookings->count();

        return response()->json([
            'earnings' => [
                'total_earnings' => $totalEarnings,
                'total_rides'    => $totalRides,
            ]
        ], 200);
    }

    // Get all drivers status (for passenger to see)
    public function getAllDriversStatus(Request $request)
    {
        $drivers = Driver::with('user')->get();

        $driverList = $drivers->map(function ($driver) {
            return [
                'id'             => $driver->id,
                'name'           => $driver->user->name ?? 'Unknown',
                'vehicle_type'   => $driver->vehicle_type,
                'vehicle_number' => $driver->vehicle_number,
                'status'         => $driver->status ?? 'offline',
                'latitude'       => $driver->latitude,
                'longitude'      => $driver->longitude,
            ];
        });

        return response()->json([
            'drivers' => $driverList
        ], 200);
    }

    // Update driver location
    public function updateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user   = $request->user();
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json(['message' => 'Driver not found'], 404);
        }

        $driver->updateLocation(
            $request->latitude,
            $request->longitude
        );

        return response()->json([
            'message'   => 'Location updated',
            'latitude'  => $driver->latitude,
            'longitude' => $driver->longitude,
        ], 200);
    }

    // Get nearby available drivers (for passenger)
    public function getNearbyDrivers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude'     => 'required|numeric',
            'longitude'    => 'required|numeric',
            'vehicle_type' => 'nullable|in:4-seater,7-seater,8-seater',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $passengerLat = $request->latitude;
        $passengerLng = $request->longitude;
        $vehicleType  = $request->vehicle_type;
        $maxDistance  = 3;

        $query = Driver::with('user')
            ->where('status', Driver::STATUS_AVAILABLE)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($vehicleType) {
            $query->where('vehicle_type', $vehicleType);
        }

        $drivers = $query->get();

        $nearbyDrivers = $drivers->filter(function ($driver) use (
            $passengerLat,
            $passengerLng,
            $maxDistance
        ) {
            $distance = $driver->distanceFrom($passengerLat, $passengerLng);
            return $distance !== null && $distance <= $maxDistance;
        })->map(function ($driver) use ($passengerLat, $passengerLng) {
            $distance = $driver->distanceFrom($passengerLat, $passengerLng);
            return [
                'id'             => $driver->id,
                'name'           => $driver->user->name ?? 'Unknown',
                'vehicle_type'   => $driver->vehicle_type,
                'vehicle_number' => $driver->vehicle_number,
                'status'         => $driver->status,
                'latitude'       => $driver->latitude,
                'longitude'      => $driver->longitude,
                'distance_km'    => $distance,
            ];
        })->sortBy('distance_km')->values();

        return response()->json([
            'drivers'            => $nearbyDrivers,
            'passenger_location' => [
                'latitude'  => $passengerLat,
                'longitude' => $passengerLng,
            ],
            'radius_km' => $maxDistance,
        ], 200);
    }

    // Get driver's own scheduled rides only
    public function getMyRides(Request $request)
    {
        $user   = $request->user();
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json(['message' => 'Driver not found'], 404);
        }

        $bookings = Booking::where('driver_id', $user->id)
            ->where('booking_type', 'scheduled')
            ->whereIn('status', ['accepted', 'in_progress', 'completed'])
            ->with(['passenger'])
            ->orderBy('scheduled_date', 'asc')
            ->orderBy('scheduled_time', 'asc')
            ->get();

        return response()->json(['bookings' => $bookings], 200);
    }

    // Delete driver's completed scheduled ride
    public function deleteMyRide(Request $request, $id)
    {
        $booking = Booking::where('id', $id)
            ->where('driver_id', $request->user()->id)
            ->where('status', 'completed')
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Ride not found'], 404);
        }

        $booking->delete();

        return response()->json(['message' => 'Ride deleted successfully'], 200);
    }
}