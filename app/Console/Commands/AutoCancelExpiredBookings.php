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
        // Find all pending NOW bookings older than 5 minutes
        $expiredBookings = Booking::where('status', 'pending')
            ->where('booking_type', 'now')
            ->where('created_at', '<', Carbon::now()->subMinutes(5))
            ->get();

        // Group by driver to send one notification per driver
        $driverBookingCount = [];

        foreach ($expiredBookings as $booking) {
            // Cancel the booking
            $booking->update(['status' => 'cancelled']);

            // Set driver back to available
            if ($booking->driver_id) {
                $driver = Driver::where('user_id', $booking->driver_id)->first();
                if ($driver) {
                    $driver->setAvailable();
                }

                // Count cancelled bookings per driver
                if (!isset($driverBookingCount[$booking->driver_id])) {
                    $driverBookingCount[$booking->driver_id] = 0;
                }
                $driverBookingCount[$booking->driver_id]++;
            }

            $this->info("Booking #{$booking->id} auto cancelled.");
        }

        // ✅ Send ONE notification per driver
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

    private function sendFCM(string $fcmToken, string $title, string $body): void
    {
        try {
            // ✅ Try environment variable first, fallback to file
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