<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            'email'          => 'required|string|email|max:255|unique:users',
            'password'       => 'required|string|min:6',
            'user_type'      => 'required|in:passenger,driver',
            'phone'          => 'required|string',
            'vehicle_type'   => 'required_if:user_type,driver|in:4-seater,7-seater,8-seater',
            'vehicle_number' => 'required_if:user_type,driver|string',
            'license_number' => 'required_if:user_type,driver|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'user_type' => $request->user_type,
            'phone'     => $request->phone,
        ]);

        if ($request->user_type === 'driver') {
            Driver::create([
                'user_id'        => $user->id,
                'vehicle_type'   => $request->vehicle_type,
                'vehicle_number' => $request->vehicle_number,
                'license_number' => $request->license_number,
                'is_available'   => false,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user'    => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'profile_photo' => $user->profile_photo
                    ? asset('storage/' . $user->profile_photo)
                    : null,
                'user_type' => $user->user_type,
            ],
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string',
            'password'  => 'required',
            'fcm_token' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('name', $request->name)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ($request->fcm_token) {
            $user->fcm_token = $request->fcm_token;
            $user->save();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user'    => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'profile_photo' => $user->profile_photo
                    ? asset('storage/' . $user->profile_photo)
                    : null,
                'user_type' => $user->user_type,
            ],
            'token' => $token,
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    public function getProfile(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user' => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'profile_photo' => $user->profile_photo
                    ? asset('storage/' . $user->profile_photo)
                    : null,
                'user_type' => $user->user_type,
            ]
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'phone'         => 'required|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $path = $request->file('profile_photo')->storePublicly('profiles', 'public');
            $user->profile_photo = $path;
        }

        $user->name  = $request->name;
        $user->phone = $request->phone;
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully!',
            'user'    => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'profile_photo' => $user->profile_photo
                    ? asset('storage/' . $user->profile_photo)
                    : null,
                'user_type' => $user->user_type,
            ]
        ], 200);
    }

    public function loadUserProfile(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user' => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'profile_photo' => $user->profile_photo
                    ? asset('storage/' . $user->profile_photo)
                    : null,
                'user_type' => $user->user_type,
            ]
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect!'
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully!'], 200);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email not found!'], 404);
        }

        $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        \DB::table('password_resets')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $token, 'created_at' => now()]
        );

        try {
            \Mail::send('emails.reset_password', ['token' => $token, 'name' => $user->name], function ($message) use ($request) {
                $message->to($request->email)
                    ->subject('DrukRide Taxi - Password Reset Code');
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send email: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'Reset code sent to your email!'
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'            => 'required|email',
            'token'            => 'required|string',
            'new_password'     => 'required|string|min:6',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reset = \DB::table('password_resets')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$reset) {
            return response()->json(['message' => 'Invalid or expired code!'], 400);
        }

        if (now()->diffInMinutes($reset->created_at) > 15) {
            return response()->json(['message' => 'Code has expired! Please request a new one.'], 400);
        }

        $user           = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->new_password);
        $user->save();

        \DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successfully!'], 200);
    }
}