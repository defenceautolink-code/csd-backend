<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get list of all users
     */
    public function index(Request $request)
    {
        try {
            $query = User::query();

            // Search by name, email, phone
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%')
                      ->orWhere('phone', 'like', '%' . $search . '%');
                });
            }

            // Filter by role
            if ($request->filled('role')) {
                $query->where('role', $request->role);
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Handle Pagination or Full List
            $isAll = $request->boolean('all') || $request->get('per_page') === 'all' || (int) $request->get('per_page') === -1 || $request->get('paginate') === 'false';

            if (!$isAll) {
                $perPage = (int) ($request->get('per_page') ?? $request->get('limit') ?? $request->get('pageSize') ?? 15);
                $perPage = $perPage > 0 ? $perPage : 15;
                $paginated = $query->latest()->paginate($perPage);

                return response()->json([
                    'status' => true,
                    'message' => 'Users retrieved successfully',
                    'data' => $paginated->items(),
                    'pagination' => [
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                        'from' => $paginated->firstItem(),
                        'to' => $paginated->lastItem(),
                    ],
                ]);
            }

            $users = $query->latest()->get();

            return response()->json([
                'status' => true,
                'message' => 'Users retrieved successfully',
                'data' => $users,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => count($users),
                    'total' => count($users),
                    'from' => count($users) > 0 ? 1 : null,
                    'to' => count($users),
                ],
                'total' => $users->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('UserController@index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching users.',
            ], 500);
        }
    }

    /**
     * Create a new user
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:6',
                'phone' => 'required|string|max:20',
                'role' => ['required', Rule::in(UserRole::values())],
                'profile_photo' => 'nullable|file|image|max:2048',
                'status' => 'nullable|string|in:Active,Inactive,active,inactive',
            ]);

            // Process Profile Photo upload if provided
            $photoPath = null;
            if ($request->hasFile('profile_photo')) {
                $file = $request->file('profile_photo');
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
                $file->move(public_path('uploads/users'), $filename);
                $photoPath = '/uploads/users/' . $filename;
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'profile_photo' => $photoPath,
                'role' => $request->role,
                'status' => $request->filled('status') ? ucfirst(strtolower($request->status)) : 'Active',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'User created successfully',
                'data' => $user,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('UserController@store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while creating user.',
            ], 500);
        }
    }

    /**
     * Get a single user
     */
    public function show($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'User details',
                'data' => $user,
            ]);
        } catch (\Throwable $e) {
            Log::error('UserController@show error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching user details.',
            ], 500);
        }
    }

    /**
     * Update an existing user
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found',
                ], 404);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'phone' => 'required|string|max:20',
                'role' => ['required', Rule::in(UserRole::values())],
                'profile_photo' => 'nullable|file|image|max:2048',
                'status' => 'nullable|string|in:Active,Inactive,active,inactive',
            ]);

            // Process Profile Photo
            $photoPath = $user->profile_photo;
            if ($request->hasFile('profile_photo')) {
                if ($user->profile_photo && file_exists(public_path($user->profile_photo))) {
                    @unlink(public_path($user->profile_photo));
                }
                $file = $request->file('profile_photo');
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
                $file->move(public_path('uploads/users'), $filename);
                $photoPath = '/uploads/users/' . $filename;
            }

            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'profile_photo' => $photoPath,
                'role' => $request->role,
                'status' => $request->filled('status') ? ucfirst(strtolower($request->status)) : $user->status,
            ];

            // Update password if provided
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            return response()->json([
                'status' => true,
                'message' => 'User updated successfully',
                'data' => $user,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('UserController@update error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while updating user.',
            ], 500);
        }
    }

    /**
     * Delete a user
     */
    public function destroy($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found',
                ], 404);
            }

            if ($user->role === 'Super Admin') {
                return response()->json([
                    'status' => false,
                    'message' => 'Super Admin accounts cannot be deleted.',
                ], 403);
            }

            if ($user->profile_photo && file_exists(public_path($user->profile_photo))) {
                @unlink(public_path($user->profile_photo));
            }

            $user->delete();

            return response()->json([
                'status' => true,
                'message' => 'User deleted successfully',
            ]);
        } catch (\Throwable $e) {
            Log::error('UserController@destroy error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while deleting user.',
            ], 500);
        }
    }
}
