<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('AuthController@login error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred during authentication.',
            ], 500);
        }
    }

    /**
     * Get authenticated user profile details
     */
    public function profile(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            if (!$user && ($request->filled('user_id') || $request->filled('id'))) {
                $user = User::find($request->input('user_id') ?? $request->input('id'));
            }

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated or user not found',
                ], 401);
            }

            return response()->json([
                'status' => true,
                'message' => 'User profile retrieved successfully',
                'data' => $user,
            ]);
        } catch (\Throwable $e) {
            Log::error('AuthController@profile error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching user profile.',
            ], 500);
        }
    }

    /**
     * Update Profile (Email & Phone Only)
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            if (!$user && ($request->filled('user_id') || $request->filled('id'))) {
                $user = User::find($request->input('user_id') ?? $request->input('id'));
            }

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated or user not found',
                ], 401);
            }

            $validated = $request->validate([
                'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
                'phone' => ['required', 'string', 'max:20'],
            ]);

            $user->email = $validated['email'];
            $user->phone = $validated['phone'];
            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'Profile updated successfully',
                'data' => $user->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('AuthController@updateProfile error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while updating profile.',
            ], 500);
        }
    }

    /**
     * Change Password
     */
    public function changePassword(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            if (!$user && ($request->filled('user_id') || $request->filled('id'))) {
                $user = User::find($request->input('user_id') ?? $request->input('id'));
            }

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated or user not found',
                ], 401);
            }

            $request->validate([
                'current_password' => ['required', 'string'],
                'new_password' => ['required', 'string', 'min:6', 'confirmed'],
            ]);

            // Verify current password using Hash::check
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Current password is incorrect',
                    'errors' => [
                        'current_password' => ['Current password is incorrect'],
                    ],
                ], 422);
            }

            // Hash new password and save in database
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'Password changed successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('AuthController@changePassword error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while changing password.',
            ], 500);
        }
    }
}
