<?php

namespace App\Http\Controllers\backend;

use Exception;
use App\Models\User;
use PharIo\Manifest\Url;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Mail\OTPVerification;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

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
            'image' => 'nullable|array|max:5',
            'image.*' => 'image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240'
        ]);


        $imagePaths = [];
        if ($request->has('image')) {
            foreach ($request->file('image') as $image) {
                $path = $image->store('img', 'public');
                $imagePaths[] = asset('storage/' . $path);
            }
        }


        $otp = rand(100000, 999999);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'location' => $validated['location'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'image' => json_encode($imagePaths),
            'otp' => $otp,
        ]);

        try {
            Mail::to($user->email)->send(new OTPVerification($otp));
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }

        $message = match ($user->role) {
            'ADMIN' => 'Welcome Admin! Please verify your email',
            'OWNER' => 'Welcome Business Owner! Please verify your email',
            default => 'Welcome User! Please verify your email',
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

            // Check email verification only for non-OWNER roles
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
        }
        return response()->json(['message' => 'Email is verified'], 200);
    }
    // update profile
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated.'], 401);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('location')) {
            $user->location = $request->location;
        }

        if ($request->has('password')) {
            $user->password = bcrypt($request->password);
        }

        $user->save();

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user], 200);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect'], 403);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully']);
    }
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $token = rand(1, 7998989898);
        $email = $request->email;
        $exist = DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => now(),
        ]);

        $resetUrl = url('api/reset-password/' . $token . '/' . $email);

        return response()->json(['reset_url' => $resetUrl]);
    }

    public function resetPassword(Request $request, $token, $email)
    {

        $request->validate([
            'password' => 'required|min:6|confirmed',
        ]);

        $tokenData = DB::table('password_reset_tokens')
            ->where('token', $token)
            ->where('email', $email)
            ->first();

        if (!$tokenData) {
            return response()->json(['error' => 'Invalid token or email.'], 400);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $user->password = bcrypt($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        return response()->json(['message' => 'Password reset successful.']);
    }

    public function logout()
    {
        auth('api')->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }
}
