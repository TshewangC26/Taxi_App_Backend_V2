<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    // Payment details (QR code, bank, mobile)
    private function getPaymentDetails()
    {
        return [
            'bank_account' => '1234567890',
            'bank_name' => 'Bank of Bhutan',
            'mobile_number' => '17XXXXXX',
            'mobile_name' => 'DrukRide Taxi',
            'qr_code' => asset('storage/payment/qr_code.png'),
        ];
    }

    // Create payment for a completed booking
    public function createPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
            'payment_method' => 'required|in:cash,online',
            'transaction_id' => 'nullable|string',
            'screenshot' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if booking exists and is completed
        $booking = Booking::find($request->booking_id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        if ($booking->status !== 'completed') {
            return response()->json(['message' => 'Booking must be completed first'], 400);
        }

        // Check if payment already exists
        $existingPayment = Payment::where('booking_id', $request->booking_id)->first();
        if ($existingPayment) {
            return response()->json(['message' => 'Payment already exists for this booking'], 400);
        }

        // Handle screenshot upload
        $screenshotPath = null;
        if ($request->hasFile('screenshot')) {
            $screenshotPath = $request->file('screenshot')->storePublicly('payments', 'public');
        }

        // Determine payment status
        $status = $request->payment_method === 'cash' ? 'completed' : 'pending';

        // Create payment
        $payment = Payment::create([
            'booking_id' => $request->booking_id,
            'amount' => $booking->final_price,
            'payment_method' => $request->payment_method,
            'status' => $status,
            'transaction_id' => $request->transaction_id,
            'screenshot' => $screenshotPath,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Payment created successfully',
            'payment' => [
                'id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'status' => $payment->status,
                'transaction_id' => $payment->transaction_id,
                'screenshot' => $payment->screenshot
                    ? asset('storage/' . $payment->screenshot)
                    : null,
                'notes' => $payment->notes,
                'created_at' => $payment->created_at,
            ]
        ], 201);
    }

    // Get payment details (QR code, bank, mobile)
    public function getPaymentInfo()
    {
        return response()->json([
            'payment_details' => $this->getPaymentDetails()
        ], 200);
    }

    // Get payment for a booking
    public function getPaymentByBooking($bookingId)
    {
        $payment = Payment::where('booking_id', $bookingId)->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'status' => $payment->status,
                'transaction_id' => $payment->transaction_id,
                'screenshot' => $payment->screenshot
                    ? asset('storage/' . $payment->screenshot)
                    : null,
                'notes' => $payment->notes,
                'created_at' => $payment->created_at,
            ]
        ], 200);
    }

    // Get all payments for current user
    public function getUserPayments(Request $request)
    {
        $user = $request->user();

        if ($user->user_type === 'passenger') {
            $payments = Payment::whereHas('booking', function ($query) use ($user) {
                $query->where('passenger_id', $user->id);
            })->with('booking')->orderBy('created_at', 'desc')->get();
        } elseif ($user->user_type === 'driver') {
            $payments = Payment::whereHas('booking', function ($query) use ($user) {
                $query->where('driver_id', $user->id);
            })->with('booking')->orderBy('created_at', 'desc')->get();
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $formattedPayments = $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'status' => $payment->status,
                'transaction_id' => $payment->transaction_id,
                'screenshot' => $payment->screenshot
                    ? asset('storage/' . $payment->screenshot)
                    : null,
                'notes' => $payment->notes,
                'created_at' => $payment->created_at,
                'booking' => $payment->booking,
            ];
        });

        return response()->json(['payments' => $formattedPayments], 200);
    }

    // Update payment status
    public function updatePaymentStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:completed,failed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $payment->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Payment status updated',
            'payment' => $payment
        ], 200);
    }
}