<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoCancelExpiredBookings extends Command
{
    protected $signature   = 'bookings:auto-cancel';
    protected $description = 'Auto cancel bookings that have been pending for more than 5 minutes';

    public function handle()
    {
        $expiredBookings = Booking::where('status', 'pending')
            ->where('booking_type', 'now')
            ->where('created_at', '<', Carbon::now()->subMinutes(5))
            ->get();

        $driverBookingCount = [];

        foreach ($expiredBookings as $booking) {
            $booking->update(['status' => 'cancelled']);

            // ✅ Update Firebase booking status
            $this->updateFirebaseBookingStatus($booking->id, 'cancelled', $booking->passenger_id);

            if ($booking->driver_id) {
                $driver = Driver::where('user_id', $booking->driver_id)->first();
                if ($driver) {
                    $driver->setAvailable();
                }

                if (!isset($driverBookingCount[$booking->driver_id])) {
                    $driverBookingCount[$booking->driver_id] = 0;
                }
                $driverBookingCount[$booking->driver_id]++;
            }

            $this->info("Booking #{$booking->id} auto cancelled.");
        }

        foreach ($driverBookingCount as $driverUserId => $count) {
            $driverUser = User::find($driverUserId);
            if ($driverUser && $driverUser->fcm_token) {
                $this->sendFCM(
                    $driverUser->fcm_token,
                    'OnlineTaxiServices',
                    'Booking cancelled as you did not accept within 5 minutes.'
                );
                $this->info("Cancellation notification sent to driver #{$driverUserId}");
            }
        }

        $this->info('Auto cancel completed. Total: ' . $expiredBookings->count());
    }

    // ✅ Update Firebase booking status
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

    private function sendFCM(string $fcmToken, string $title, string $body): void
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
                        'notification' => ['sound' => 'default'],
                    ],
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
            \Log::error('FCM Auto Cancel Error: ' . $e->getMessage());
        }
    }
}