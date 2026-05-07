<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Console\Command;

class SendRideReminders extends Command
{
    protected $signature   = 'reminders:send';
    protected $description = 'Send ride reminder notifications to passengers and drivers';

    public function handle(): void
    {
        $now = now();

        // ── PASSENGER: 10 min before scheduled ride ──
        $passengerReminders = Booking::where('booking_type', 'scheduled')
            ->where('status', 'accepted')
            ->whereDate('scheduled_date', $now->toDateString())
            ->get()
            ->filter(function ($booking) use ($now) {
                if (!$booking->scheduled_time) return false;
                $rideTime = \Carbon\Carbon::parse(
                    $booking->scheduled_date . ' ' . $booking->scheduled_time
                );
                $minutesUntilRide = $now->diffInMinutes($rideTime, false);
                return $minutesUntilRide >= 9 && $minutesUntilRide <= 11;
            });

        foreach ($passengerReminders as $booking) {
            $passenger = User::find($booking->passenger_id);
            if ($passenger && $passenger->fcm_token) {
                $this->sendFCM(
                    $passenger->fcm_token,
                    'OnlineTaxiServices',
                    'Your ride starts in 10 minutes!'
                );
                $this->info("Passenger reminder sent for booking #{$booking->id}");
            }
        }

        // ── DRIVER: 5 min before scheduled ride ──
        $driverReminders = Booking::where('booking_type', 'scheduled')
            ->where('status', 'accepted')
            ->whereDate('scheduled_date', $now->toDateString())
            ->get()
            ->filter(function ($booking) use ($now) {
                if (!$booking->scheduled_time) return false;
                $rideTime = \Carbon\Carbon::parse(
                    $booking->scheduled_date . ' ' . $booking->scheduled_time
                );
                $minutesUntilRide = $now->diffInMinutes($rideTime, false);
                return $minutesUntilRide >= 4 && $minutesUntilRide <= 6;
            });

        foreach ($driverReminders as $booking) {
            $driver = User::find($booking->driver_id);
            if ($driver && $driver->fcm_token) {
                $this->sendFCM(
                    $driver->fcm_token,
                    'OnlineTaxiServices',
                    'Your scheduled ride starts in 5 minutes!'
                );
                $this->info("Driver reminder sent for booking #{$booking->id}");
            }
        }

        $this->info('Ride reminders check completed at ' . $now->format('H:i:s'));
    }

    private function sendFCM(string $fcmToken, string $title, string $body): void
    {
        try {
            $credentialsPath = storage_path('firebase-adminsdk.json');
            if (!file_exists($credentialsPath)) return;

            $credentials = json_decode(file_get_contents($credentialsPath), true);

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
            \Log::error('FCM Reminder Error: ' . $e->getMessage());
        }
    }
}