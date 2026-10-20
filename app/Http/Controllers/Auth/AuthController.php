<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\ResetPasswordMail;
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

    /**
     * Send Password Reset Link to Email (4-minute validity)
     */
    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => ['required', 'email'],
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'No account found with this email address.',
                    'errors' => [
                        'email' => ['No account found with this email address.'],
                    ],
                ], 404);
            }

            // Generate secure 64-character random token
            $rawToken = Str::random(64);
            $hashedToken = Hash::make($rawToken);

            // Store in password_reset_tokens table with current timestamp
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => $hashedToken,
                    'created_at' => Carbon::now(),
                ]
            );

            // Dynamically detect frontend origin (works seamlessly across localhost & live staging/prod)
            $frontendUrl = $request->header('origin');
            if (!$frontendUrl) {
                $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            }

            $resetUrl = rtrim($frontendUrl, '/') . '/reset-password?token=' . urlencode($rawToken) . '&email=' . urlencode($user->email);

            // Send branded reset email (4 minutes validity)
            Mail::to($user->email)->send(new ResetPasswordMail($user, $resetUrl, 4));

            return response()->json([
                'status' => true,
                'message' => 'Password reset link has been sent to your email successfully. Please check your inbox.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('AuthController@forgotPassword error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to send password reset email. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset Password with Token (Strict 4-minute validity)
     */
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => ['required', 'email'],
                'token' => ['required', 'string'],
                'password' => ['required', 'string', 'min:6', 'confirmed'],
            ]);

            $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

            if (!$record || !Hash::check($request->token, $record->token)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid or expired password reset link.',
                    'errors' => [
                        'token' => ['This password reset link is invalid or has already been used.'],
                    ],
                ], 422);
            }

            // Check 4-minute validity window
            $createdAt = Carbon::parse($record->created_at);
            if ($createdAt->copy()->addMinutes(4)->isPast()) {
                // Delete expired token
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();

                return response()->json([
                    'status' => false,
                    'message' => 'The password reset link has expired (it was valid for 4 minutes). Please request a new link.',
                    'errors' => [
                        'token' => ['The password reset link has expired (validity is 4 minutes). Please request a new link.'],
                    ],
                ], 422);
            }

            // Find user and update password
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $user->password = Hash::make($request->password);
            $user->save();

            // Invalidate the token immediately so it cannot be reused
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'status' => true,
                'message' => 'Password has been reset successfully! Redirecting to login...',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('AuthController@resetPassword error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while resetting password.',
            ], 500);
        }
    }
}
