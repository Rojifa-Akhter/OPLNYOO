<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Mail\OTPVerification;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    //registration
    public function signup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'location' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:ADMIN,OWNER,USER',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
        ]);
        $imagePath = null;
        if ($request->has('image')) {
            $image = $request->file('image');
            $path = $image->store('profile_images', 'public');
            $imagePath = asset('storage/' . $path);
        }

        $otp = rand(100000, 999999);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'location' => $validated['location'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'image' => $imagePath,
            'otp' => $otp,
        ]);

        try {
            Mail::to($user->email)->send(new OTPVerification($otp));
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }

        $message = match ($user->role) {
            'ADMIN' => 'Welcome Admin! Please verify your email.',
            'OWNER' => 'Welcome Business Owner! Please verify your email.',
            default => 'Welcome User! Please verify your email.',
        };

        return response()->json(['message' => $message], 200);
    }

    //social login
    public function socialLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $existingUser = User::where('email', $request->email)->first();

        if ($existingUser) {
            $socialMatch = ($request->has('google_id') && $existingUser->google_id === $request->google_id) ||
                ($request->has('facebook_id') && $existingUser->facebook_id === $request->facebook_id);

            if ($socialMatch) {
                $token = JWTAuth::fromUser($existingUser);
                return response()->json(['access_token' => $token, 'token_type' => 'bearer']);
            } elseif (is_null($existingUser->google_id) && is_null($existingUser->facebook_id)) {
                return response()->json(['message' => 'User already exists. Sign in manually.'], 422);
            } else {
                $existingUser->update([
                    'google_id' => $request->google_id ?? $existingUser->google_id,
                    'facebook_id' => $request->facebook_id ?? $existingUser->facebook_id,
                ]);
                $token = JWTAuth::fromUser($existingUser);
                return response()->json(['access_token' => $token, 'token_type' => 'bearer']);
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make(Str::random(16)),
            'role' => 'USER',
            'google_id' => $request->google_id ?? null,
            'facebook_id' => $request->facebook_id ?? null,
            'location' => $request->location ?? null,
            'verify_email' => true,
            'status' => 'active',
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }

    //login
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if ($token = Auth::guard('api')->attempt($credentials)) {
            $user = Auth::guard('api')->user();

            if ($user->role !== 'OWNER' && !$user->hasVerifiedEmail()) {
                return response()->json(['error' => 'Email not verified. Please check your email.'], 403);
            }

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at,
            ]);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response($validator->messages(), 200);
        }

        $user = User::where('otp', $request->otp)->first();

        if ($user) {
            $user->otp = null;
            $user->email_verified_at = now();
            $user->save();

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'message' => 'Email is verified',
                'access_token' => $token,
                'token_type' => 'bearer',
                'email_verified_at' => $user->email_verified_at,
            ], 200);
        }

        return response()->json(['error' => 'Invalid OTP.'], 400);
    }

    public function guard()
    {
        return Auth::guard('api');
    }
    public function updateProfile(Request $request)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json(['error' => 'User not authenticated.'], 401);
        }

        $validatedData = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'location' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:6|confirmed',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
        ]);

        if ($request->has('name')) {
            $user->name = $validatedData['name'];
        }
        if ($request->has('email')) {
            $user->email = $validatedData['email'];
        }
        if ($request->has('location')) {
            $user->location = $validatedData['location'];
        }
        if ($request->has('password')) {
            $user->password = Hash::make($validatedData['password']);
        }

        if ($request->has('image')) {
            $image = $request->file('image');

            if ($image->isValid()) {
                $path = $image->store('profile_images', 'public');
                $imagePath = asset('storage/' . $path);

                $user->image = $imagePath;
            } else {
                return response()->json(['error' => 'The image failed to upload.'], 400);
            }
        } elseif (!$request->has('image') && !$user->image) {
            $user->image = null;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'location' => $user->location,
                'image' => $user->image,
            ],
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'User not authenticated.'], 401);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect.'], 403);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['error' => 'Email not registered.'], 404);
        }
        $otp = rand(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $otp, 'created_at' => now()]
        );

        try {
            Mail::to($request->email)->send(new OTPVerification($otp));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to send OTP.'], 500);
        }

        return response()->json(['message' => 'OTP sent to your email.'], 200);
    }
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|numeric',
        ]);

        $tokenData = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->otp)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->first();

        if (!$tokenData) {
            return response()->json(['error' => 'Invalid or expired OTP.'], 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $resetToken = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'OTP verified successfully.',
            'reset_token' => $resetToken,
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $user->password = bcrypt($request->password);
        $user->save();

        return response()->json(['message' => 'Password reset successful.'], 200);
    }

    public function resendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['error' => 'Email not registered.'], 404);
        }

        $otp = rand(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $otp, 'created_at' => now()]
        );

        try {
            Mail::to($request->email)->send(new OTPVerification($otp));
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to resend OTP.'], 500);
        }

        return response()->json(['message' => 'OTP resent to your email.'], 200);
    }

    public function logout()
    {
        auth('api')->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }
    public function userData()
    {
        $users = User::all()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'location' => $user->location,
                'avatar' => $user->image ? asset('storage/' . $user->image) : url('/img/3.jpg'),
                'role' => $user->role,
                'description' => $user->description,
                'google_id' => $user->google_id,
            ];
        });

        return response()->json($users);
    }

}
