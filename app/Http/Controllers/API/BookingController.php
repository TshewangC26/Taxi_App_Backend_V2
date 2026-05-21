<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Route;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    // ── FCM HELPER ──
    private function sendFCM(string $fcmToken, string $title, string $body, array $data = []): void
    {
        try {
            $credentialsJson = env('FIREBASE_CREDENTIALS');
            if ($credentialsJson) {
                $credentials = json_decode($credentialsJson, true);
            } else {
                $credentialsPath = storage_path('firebase-adminsdk.json');
                if (!file_exists($credentialsPath)) return;
                $credentials = json_decode(file_get_contents($credentialsPath), true);
            }
            if (!$credentials) return;

            $now     = time();
            $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
            $payload = rtrim(strtr(base64_encode(json_encode([
                'iss'   => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'exp'   => $now + 3600,
                'iat'   => $now,
            ])), '+/', '-_'), '=');

            $signingInput = $header . '.' . $payload;
            openssl_sign($signingInput, $signature, $credentials['private_key'], 'SHA256');
            $signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
            $jwt       = $signingInput . '.' . $signature;

            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $tokenResponse = json_decode(curl_exec($ch), true);
            curl_close($ch);

            $accessToken = $tokenResponse['access_token'] ?? '';
            if (empty($accessToken)) return;

            $projectId = $credentials['project_id'];
            $url       = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            $message = [
                'message' => [
                    'token'        => $fcmToken,
                    'notification' => ['title' => $title, 'body' => $body],
                    'android'      => [
                        'priority'     => 'high',
                        'notification' => [
                            'sound'        => 'default',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ],
                    ],
                    'data' => array_map('strval', $data),
                ],
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            \Log::error('FCM Error: ' . $e->getMessage());
        }
    }

    // ── Update Firebase booking status ──
    private function updateFirebaseBookingStatus(int $bookingId, string $status, int $passengerId): void
    {
        try {
            $firebaseUrl = env('FIREBASE_DATABASE_URL', 'https://taxiapp-e5f40-default-rtdb.asia-southeast1.firebasedatabase.app');
            $data = json_encode([
                'status'       => $status,
                'passenger_id' => $passengerId,
                'updated_at'   => round(microtime(true) * 1000),
            ]);
            $ch = curl_init("{$firebaseUrl}/bookings/{$bookingId}.json");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            \Log::error('Firebase update error: ' . $e->getMessage());
        }
    }

    // Create a new booking (Passenger)
    public function createBooking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pickup_location'     => 'required|string',
            'dropoff_location'    => 'required|string',
            'vehicle_type'        => 'required|in:4-seater,7-seater,8-seater',
            'booking_type'        => 'required|in:now,scheduled',
            'scheduled_date'      => 'required_if:booking_type,scheduled|date',
            'scheduled_time'      => 'required_if:booking_type,scheduled',
            'driver_id'           => 'nullable|integer',
            'passenger_latitude'  => 'nullable|numeric',
            'passenger_longitude' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $route = Route::where('pickup_location', $request->pickup_location)
            ->where('dropoff_location', $request->dropoff_location)
            ->where('is_active', true)
            ->first();

        if (!$route) {
            return response()->json(['message' => 'Route not found'], 404);
        }

        $priceField     = 'price_' . str_replace('-', '_', $request->vehicle_type);
        $estimatedPrice = $route->$priceField;
        $status         = 'pending';
        $driverId       = null;

        if ($request->driver_id) {
            $driver = Driver::where('id', $request->driver_id)->first();

            if ($request->booking_type === 'now') {
                if (!$driver || $driver->status !== Driver::STATUS_AVAILABLE) {
                    return response()->json([
                        'message' => 'Selected driver is no longer available. Please choose another.'
                    ], 400);
                }
            } else {
                if (!$driver) {
                    return response()->json(['message' => 'Selected driver not found.'], 400);
                }
            }

            $driverId = $driver->user_id;
        }

        $booking = Booking::create([
            'passenger_id'        => $request->user()->id,
            'driver_id'           => $driverId,
            'pickup_location'     => $request->pickup_location,
            'dropoff_location'    => $request->dropoff_location,
            'vehicle_type'        => $request->vehicle_type,
            'estimated_price'     => $estimatedPrice,
            'status'              => $status,
            'booking_type'        => $request->booking_type,
            'scheduled_date'      => $request->scheduled_date,
            'scheduled_time'      => $request->scheduled_time,
            'passenger_latitude'  => $request->passenger_latitude,
            'passenger_longitude' => $request->passenger_longitude,
        ]);

        // Notify driver
        if ($driverId) {
            $driverUser = User::find($driverId);
            if ($driverUser && $driverUser->fcm_token) {
                $this->sendFCM(
                    $driverUser->fcm_token,
                    'OnlineTaxiServices',
                    'New booking received!',
                    ['booking_id' => (string) $booking->id, 'type' => 'new_booking']
                );
            }
        }

        return response()->json([
            'message' => 'Booking created successfully',
            'booking' => $booking
        ], 201);
    }

    // Get passenger's bookings
    public function getPassengerBookings(Request $request)
    {
        $expiredBookings = Booking::where('passenger_id', $request->user()->id)
            ->where('status', 'pending')
            ->where('booking_type', 'now')
            ->where('created_at', '<', now()->subMinutes(5))
            ->get();

        foreach ($expiredBookings as $booking) {
            $booking->update(['status' => 'cancelled']);
            if ($booking->driver_id) {
                $driver = Driver::where('user_id', $booking->driver_id)->first();
                if ($driver) $driver->setAvailable();
            }
        }

        $bookings = Booking::where('passenger_id', $request->user()->id)
            ->with(['driver'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($booking) {
                $data = $booking->toArray();
                if ($booking->driver_id) {
                    $driver = Driver::where('user_id', $booking->driver_id)->first();
                    $data['driver_firebase_id'] = $driver?->id;
                } else {
                    $data['driver_firebase_id'] = null;
                }
                return $data;
            });

        return response()->json(['bookings' => $bookings], 200);
    }

    // Get available bookings for drivers
    public function getAvailableBookings(Request $request)
    {
        $user   = $request->user();
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver || $driver->status === Driver::STATUS_OFFLINE) {
            return response()->json([
                'now_bookings'       => [],
                'scheduled_bookings' => [],
                'my_active_bookings' => [],
                'message'            => 'You are offline. Turn on availability to see rides.'
            ], 200);
        }

        $nowBookings = Booking::where('status', 'pending')
            ->where('booking_type', 'now')
            ->where('driver_id', $user->id)
            ->with(['passenger'])
            ->orderBy('created_at', 'asc')
            ->get();

        $scheduledBookings = Booking::where('status', 'pending')
            ->where('booking_type', 'scheduled')
            ->where('driver_id', $user->id)
            ->where(function ($query) {
                $query->where('scheduled_date', '>', now()->toDateString())
                    ->orWhere(function ($query) {
                        $query->where('scheduled_date', now()->toDateString())
                            ->where('scheduled_time', '>=', now()->format('H:i'));
                    });
            })
            ->with(['passenger'])
            ->orderBy('scheduled_date', 'asc')
            ->orderBy('scheduled_time', 'asc')
            ->get();

        $myActiveBookings = Booking::where('driver_id', $user->id)
            ->whereIn('status', ['accepted', 'in_progress'])
            ->where('booking_type', 'now')
            ->with(['passenger'])
            ->orderBy('created_at', 'asc')
            ->get();

        // ── Also include accepted scheduled bookings ──
        $acceptedScheduled = Booking::where('driver_id', $user->id)
            ->where('status', 'accepted')
            ->where('booking_type', 'scheduled')
            ->with(['passenger'])
            ->orderBy('scheduled_date', 'asc')
            ->get();

        return response()->json([
            'now_bookings'                => $nowBookings,
            'scheduled_bookings'          => $scheduledBookings,
            'my_active_bookings'          => $myActiveBookings,
            'accepted_scheduled_bookings' => $acceptedScheduled,
        ], 200);
    }

    // Accept a booking (Driver)
    public function acceptBooking(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (!$booking) return response()->json(['message' => 'Booking not found'], 404);
        if ($booking->status !== 'pending') return response()->json(['message' => 'Booking is not available'], 400);
        if ($booking->driver_id !== $request->user()->id) return response()->json(['message' => 'Unauthorized'], 403);

        $booking->update(['status' => 'accepted']);
        $driver = Driver::where('user_id', $request->user()->id)->first();

        if ($booking->booking_type === 'now') {
            Booking::where('driver_id', $request->user()->id)
                ->where('status', 'pending')
                ->where('booking_type', 'now')
                ->where('id', '!=', $id)
                ->update(['status' => 'cancelled']);
            $driver->setBooked();
        }

        $this->updateFirebaseBookingStatus($booking->id, 'accepted', $booking->passenger_id);

        $passenger = User::find($booking->passenger_id);
        if ($passenger && $passenger->fcm_token) {
            $message = $booking->booking_type === 'scheduled'
                ? 'Scheduled ride confirmed!'
                : 'Accepted the ride';

            $this->sendFCM(
                $passenger->fcm_token,
                'OnlineTaxiServices',
                $message,
                ['booking_id' => (string) $booking->id, 'type' => 'accepted']
            );
        }

        return response()->json(['message' => 'Booking accepted!', 'booking' => $booking], 200);
    }

    // Start a ride (Driver)
    public function startRide(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (!$booking) return response()->json(['message' => 'Booking not found'], 404);
        if ($booking->driver_id !== $request->user()->id) return response()->json(['message' => 'Unauthorized'], 403);
        if ($booking->status !== 'accepted') return response()->json(['message' => 'Booking must be accepted first'], 400);

        $booking->update(['status' => 'in_progress']);
        $driver = Driver::where('user_id', $request->user()->id)->first();
        $driver->setBooked();

        $this->updateFirebaseBookingStatus($booking->id, 'in_progress', $booking->passenger_id);

        $passenger = User::find($booking->passenger_id);
        if ($passenger && $passenger->fcm_token) {
            $this->sendFCM(
                $passenger->fcm_token,
                'DrukRide',
                'Driver is coming to pick you up!',
                ['booking_id' => (string) $booking->id, 'type' => 'in_progress']
            );
        }

        return response()->json(['message' => 'Ride started', 'booking' => $booking], 200);
    }

    // Complete a ride (Driver)
    public function completeRide(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (!$booking) return response()->json(['message' => 'Booking not found'], 404);
        if ($booking->driver_id !== $request->user()->id) return response()->json(['message' => 'Unauthorized'], 403);
        if ($booking->status !== 'in_progress') return response()->json(['message' => 'Ride must be started first'], 400);

        $booking->update([
            'status'      => 'completed',
            'final_price' => $booking->estimated_price,
        ]);

        $driver = Driver::where('user_id', $request->user()->id)->first();
        $driver->setAvailable();

        $this->updateFirebaseBookingStatus($booking->id, 'completed', $booking->passenger_id);

        return response()->json(['message' => 'Ride completed! Passenger can now pay.', 'booking' => $booking], 200);
    }

    // Cancel a booking (Driver or Passenger)
    public function cancelBooking(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (!$booking) return response()->json(['message' => 'Booking not found'], 404);

        if ($booking->passenger_id !== $request->user()->id &&
            $booking->driver_id   !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($booking->status === 'completed') {
            return response()->json(['message' => 'Cannot cancel completed booking'], 400);
        }

        $reason = $request->input('cancellation_reason', null);

        $booking->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        $this->updateFirebaseBookingStatus($booking->id, 'cancelled', $booking->passenger_id);

        $cancelledByDriver    = $booking->driver_id === $request->user()->id;
        $cancelledByPassenger = $booking->passenger_id === $request->user()->id;
        $reasonText           = $reason ? " Reason: {$reason}" : '';

        if ($cancelledByDriver) {
            $passenger = User::find($booking->passenger_id);
            if ($passenger && $passenger->fcm_token) {
                $this->sendFCM(
                    $passenger->fcm_token,
                    'Booking Cancelled',
                    "Driver cancelled your scheduled ride.{$reasonText}",
                    ['booking_id' => (string) $booking->id, 'type' => 'cancelled']
                );
            }
            $driver = Driver::where('user_id', $request->user()->id)->first();
            if ($driver) $driver->setAvailable();
        }

        if ($cancelledByPassenger) {
            if ($booking->driver_id) {
                $driverUser = User::find($booking->driver_id);
                if ($driverUser && $driverUser->fcm_token) {
                    $this->sendFCM(
                        $driverUser->fcm_token,
                        'Booking Cancelled',
                        "Passenger cancelled the scheduled ride.{$reasonText}",
                        ['booking_id' => (string) $booking->id, 'type' => 'cancelled']
                    );
                }
                $driver = Driver::where('user_id', $booking->driver_id)->first();
                if ($driver) $driver->setAvailable();
            }
        }

        return response()->json([
            'message' => 'Booking cancelled',
            'booking' => $booking
        ], 200);
    }

    // Delete a booking (Passenger)
    public function deleteBooking(Request $request, $id)
    {
        $booking = Booking::where('id', $id)
            ->where('passenger_id', $request->user()->id)
            ->whereIn('status', ['completed', 'cancelled'])
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $booking->delete();

        return response()->json(['message' => 'Booking deleted successfully'], 200);
    }

    // ✅ Rate a driver (Passenger only)
    public function rateDriver(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        // Only the passenger of this booking can rate
        if ($booking->passenger_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Allow rating for completed rides AND cancelled scheduled rides
        if (!in_array($booking->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Can only rate completed or cancelled rides'], 400);
        }

        // For cancelled bookings, only allow if it was a scheduled booking (driver cancelled)
        if ($booking->status === 'cancelled' && $booking->booking_type !== 'scheduled') {
            return response()->json(['message' => 'Can only rate cancelled scheduled rides'], 400);
        }

        // Cannot rate twice
        if ($booking->rating !== null) {
            return response()->json(['message' => 'You have already rated this ride'], 400);
        }

        $validator = Validator::make($request->all(), [
            'rating'         => 'required|integer|min:1|max:5',
            'rating_comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Save rating to booking
        $booking->update([
            'rating'         => $request->rating,
            'rating_comment' => $request->rating_comment,
        ]);

        // Recalculate driver's average rating
        $driver = Driver::where('user_id', $booking->driver_id)->first();
        if ($driver) {
            $avg   = Booking::where('driver_id', $booking->driver_id)
                        ->whereNotNull('rating')
                        ->avg('rating');
            $total = Booking::where('driver_id', $booking->driver_id)
                        ->whereNotNull('rating')
                        ->count();
            $driver->update([
                'average_rating' => round($avg, 2),
                'total_ratings'  => $total,
            ]);
        }

        return response()->json([
            'message' => 'Rating submitted successfully',
            'rating'  => $request->rating,
        ], 200);
    }
}