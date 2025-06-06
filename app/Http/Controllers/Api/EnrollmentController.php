<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class EnrollmentController extends Controller
{
    /**
     * Get user's enrollments
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $query = $user->enrollments()->with('program');

            if ($request->has('status')) {
                switch ($request->status) {
                    case 'active':
                        $query->active();
                        break;
                    case 'inactive':
                        $query->where('is_active', false);
                        break;
                    case 'paid':
                        $query->paid();
                        break;
                    case 'unpaid':
                        $query->unpaid();
                        break;
                }
            }

            // Sort
            $allowedSorts = ['created_at', 'updated_at', 'paid_at'];
            $sortBy = in_array($request->get('sort_by'), $allowedSorts) ? $request->get('sort_by') : 'created_at';
            $sortOrder = in_array($request->get('sort_order'), ['asc', 'desc']) ? $request->get('sort_order') : 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = min($request->get('per_page', 10), 50);
            $enrollments = $query->paginate($perPage);

            // Transform data
            $transformedData = $enrollments->getCollection()->map(function($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'user_id' => $enrollment->user_id,
                    'program_id' => $enrollment->program_id,
                    'is_active' => $enrollment->is_active,
                    'is_paid' => $enrollment->is_paid,
                    'paid_at' => $enrollment->paid_at ? $enrollment->paid_at->format('Y-m-d H:i:s') : null,
                    'status' => $enrollment->status,
                    'created_at' => $enrollment->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $enrollment->updated_at->format('Y-m-d H:i:s'),
                    'program' => $enrollment->program ? [
                        'id' => $enrollment->program->id,
                        'title' => $enrollment->program->title,
                        'slug' => $enrollment->program->slug,
                        'price' => $enrollment->program->price,
                        'formatted_price' => $enrollment->program->formatted_price ?? 'Rp ' . number_format($enrollment->program->price, 0, ',', '.'),
                        'is_free' => $enrollment->program->price == 0,
                        'is_active' => $enrollment->program->is_active,
                    ] : null
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Enrollments retrieved successfully',
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $enrollments->currentPage(),
                    'per_page' => $enrollments->perPage(),
                    'total' => $enrollments->total(),
                    'last_page' => $enrollments->lastPage(),
                    'from' => $enrollments->firstItem(),
                    'to' => $enrollments->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get enrollments error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve enrollments',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Enroll user to a program
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'program_id' => 'required|uuid|exists:programs,id'
        ], [
            'program_id.required' => 'Program ID wajib diisi',
            'program_id.uuid' => 'Format Program ID tidak valid',
            'program_id.exists' => 'Program tidak ditemukan'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $program = Program::findOrFail($request->program_id);

            if (!$program->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Program is not active'
                ], 400);
            }

            $existingEnrollment = Enrollment::where('user_id', $user->id)
                                          ->where('program_id', $program->id)
                                          ->first();

            if ($existingEnrollment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Already enrolled in this program',
                    'data' => [
                        'enrollment_id' => $existingEnrollment->id,
                        'status' => $existingEnrollment->status
                    ]
                ], 400);
            }

            // Create enrollment
            $enrollment = Enrollment::create([
                'user_id' => $user->id,
                'program_id' => $program->id,
                'paid_at' => $program->price == 0 ? now() : null // Auto paid for free programs
            ]);

            $enrollment->load('program');

            return response()->json([
                'status' => 'success',
                'message' => 'Successfully enrolled in program',
                'data' => [
                    'id' => $enrollment->id,
                    'user_id' => $enrollment->user_id,
                    'program_id' => $enrollment->program_id,
                    'is_active' => $enrollment->is_active,
                    'is_paid' => $enrollment->is_paid,
                    'paid_at' => $enrollment->paid_at ? $enrollment->paid_at->format('Y-m-d H:i:s') : null,
                    'status' => $enrollment->status,
                    'created_at' => $enrollment->created_at->format('Y-m-d H:i:s'),
                    'program' => [
                        'id' => $enrollment->program->id,
                        'title' => $enrollment->program->title,
                        'price' => $enrollment->program->price,
                        'formatted_price' => 'Rp ' . number_format($enrollment->program->price, 0, ',', '.'),
                        'is_free' => $enrollment->program->price == 0
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Create enrollment error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'program_id' => $request->program_id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to enroll in program',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get enrollment detail
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $enrollment = Enrollment::where('id', $id)
                                  ->where('user_id', $user->id)
                                  ->with('program')
                                  ->first();

            if (!$enrollment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Enrollment not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Enrollment retrieved successfully',
                'data' => [
                    'id' => $enrollment->id,
                    'user_id' => $enrollment->user_id,
                    'program_id' => $enrollment->program_id,
                    'is_active' => $enrollment->is_active,
                    'is_paid' => $enrollment->is_paid,
                    'paid_at' => $enrollment->paid_at ? $enrollment->paid_at->format('Y-m-d H:i:s') : null,
                    'status' => $enrollment->status,
                    'created_at' => $enrollment->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $enrollment->updated_at->format('Y-m-d H:i:s'),
                    'program' => $enrollment->program ? [
                        'id' => $enrollment->program->id,
                        'title' => $enrollment->program->title,
                        'description' => $enrollment->program->description,
                        'slug' => $enrollment->program->slug,
                        'price' => $enrollment->program->price,
                        'formatted_price' => 'Rp ' . number_format($enrollment->program->price, 0, ',', '.'),
                        'is_free' => $enrollment->program->price == 0,
                        'is_active' => $enrollment->program->is_active,
                        'images' => $enrollment->program->images ?? []
                    ] : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get enrollment error: ' . $e->getMessage(), [
                'enrollment_id' => $id,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve enrollment',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update enrollment (mark as paid, activate/deactivate)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'sometimes|boolean',
            'mark_as_paid' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $enrollment = Enrollment::where('id', $id)
                                  ->where('user_id', $user->id)
                                  ->first();

            if (!$enrollment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Enrollment not found'
                ], 404);
            }

            $updateData = [];
            
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }
            
            if ($request->has('mark_as_paid') && $request->mark_as_paid) {
                $updateData['paid_at'] = now();
            }

            $enrollment->update($updateData);
            $enrollment->load('program');

            return response()->json([
                'status' => 'success',
                'message' => 'Enrollment updated successfully',
                'data' => [
                    'id' => $enrollment->id,
                    'user_id' => $enrollment->user_id,
                    'program_id' => $enrollment->program_id,
                    'is_active' => $enrollment->is_active,
                    'is_paid' => $enrollment->is_paid,
                    'paid_at' => $enrollment->paid_at ? $enrollment->paid_at->format('Y-m-d H:i:s') : null,
                    'status' => $enrollment->status,
                    'updated_at' => $enrollment->updated_at->format('Y-m-d H:i:s'),
                    'program' => [
                        'id' => $enrollment->program->id,
                        'title' => $enrollment->program->title,
                        'price' => $enrollment->program->price
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Update enrollment error: ' . $e->getMessage(), [
                'enrollment_id' => $id,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update enrollment',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Cancel enrollment
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $enrollment = Enrollment::where('id', $id)
                                  ->where('user_id', $user->id)
                                  ->first();

            if (!$enrollment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Enrollment not found'
                ], 404);
            }

            $programTitle = $enrollment->program ? $enrollment->program->title : 'Unknown Program';
            $enrollment->delete();

            return response()->json([
                'status' => 'success',
                'message' => "Enrollment for '{$programTitle}' cancelled successfully"
            ]);

        } catch (\Exception $e) {
            Log::error('Cancel enrollment error: ' . $e->getMessage(), [
                'enrollment_id' => $id,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel enrollment',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

}