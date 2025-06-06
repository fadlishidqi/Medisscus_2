<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProgramController extends Controller
{
    /**
     * Get all programs with filtering, search, and pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Program::query();

            // Search by title or description
            if ($request->has('search') && $request->search !== '') {
                $query->search($request->search);
            }

            // Filter by status
            if ($request->has('status')) {
                if ($request->status === 'active') {
                    $query->active();
                } elseif ($request->status === 'inactive') {
                    $query->where('is_active', false);
                }
            }

            // Filter by price range
            if ($request->has('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            if ($request->has('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }

            // Filter free programs
            if ($request->has('is_free') && $request->is_free == 'true') {
                $query->where('price', 0);
            }

            // Sort
            $allowedSorts = ['title', 'price', 'created_at', 'updated_at'];
            $sortBy = in_array($request->get('sort_by'), $allowedSorts) ? $request->get('sort_by') : 'created_at';
            $sortOrder = in_array($request->get('sort_order'), ['asc', 'desc']) ? $request->get('sort_order') : 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // With enrollment count
            $query->withCount('activeEnrollments');

            // Pagination
            $perPage = min($request->get('per_page', 10), 50); // Max 50 per page
            $programs = $query->paginate($perPage);

            // Transform data
            $transformedData = $programs->getCollection()->map(function($program) {
                return [
                    'id' => $program->id,
                    'title' => $program->title,
                    'description' => $program->description,
                    'slug' => $program->slug,
                    'price' => $program->price,
                    'formatted_price' => $program->formatted_price,
                    'is_free' => $program->is_free,
                    'is_active' => $program->is_active,
                    'images' => $program->image_urls,
                    'enrollment_count' => $program->active_enrollments_count,
                    'created_at' => $program->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $program->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Programs retrieved successfully',
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $programs->currentPage(),
                    'per_page' => $programs->perPage(),
                    'total' => $programs->total(),
                    'last_page' => $programs->lastPage(),
                    'from' => $programs->firstItem(),
                    'to' => $programs->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get programs error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve programs',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create new program
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255|unique:programs,title',
            'description' => 'required|string|min:10',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'images' => 'nullable|array|max:5',
            'images.*' => 'nullable|string|max:2048',
            'is_active' => 'sometimes|boolean'
        ], [
            'title.required' => 'Judul program wajib diisi',
            'title.unique' => 'Judul program sudah ada',
            'description.required' => 'Deskripsi program wajib diisi',
            'description.min' => 'Deskripsi minimal 10 karakter',
            'price.required' => 'Harga program wajib diisi',
            'price.numeric' => 'Harga harus berupa angka',
            'price.min' => 'Harga tidak boleh negatif',
            'images.max' => 'Maksimal 5 gambar',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Validate images if provided
            $images = [];
            if ($request->has('images') && is_array($request->images)) {
                foreach ($request->images as $image) {
                    if (filter_var($image, FILTER_VALIDATE_URL)) {
                        $images[] = $image;
                    } else {
                        // Assume it's a base64 or file path
                        $images[] = $image;
                    }
                }
            }

            $program = Program::create([
                'title' => $request->title,
                'description' => $request->description,
                'price' => $request->price,
                'images' => $images,
                'is_active' => $request->get('is_active', true)
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Program created successfully',
                'data' => [
                    'id' => $program->id,
                    'title' => $program->title,
                    'description' => $program->description,
                    'slug' => $program->slug,
                    'price' => $program->price,
                    'formatted_price' => $program->formatted_price,
                    'is_free' => $program->is_free,
                    'is_active' => $program->is_active,
                    'images' => $program->image_urls,
                    'created_at' => $program->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $program->updated_at->format('Y-m-d H:i:s'),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Create program error: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create program',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get program by ID or slug
     */
    public function show($identifier): JsonResponse
    {
        try {
            // Try to find by ID first, then by slug
            $program = Program::where('id', $identifier)
                            ->orWhere('slug', $identifier)
                            ->withCount('activeEnrollments')
                            ->first();

            if (!$program) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Program not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Program retrieved successfully',
                'data' => [
                    'id' => $program->id,
                    'title' => $program->title,
                    'description' => $program->description,
                    'slug' => $program->slug,
                    'price' => $program->price,
                    'formatted_price' => $program->formatted_price,
                    'is_free' => $program->is_free,
                    'is_active' => $program->is_active,
                    'images' => $program->image_urls,
                    'enrollment_count' => $program->active_enrollments_count,
                    'created_at' => $program->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $program->updated_at->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get program error: ' . $e->getMessage(), [
                'identifier' => $identifier,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve program',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update program
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255|unique:programs,title,' . $id,
            'description' => 'sometimes|string|min:10',
            'price' => 'sometimes|numeric|min:0|max:99999999.99',
            'images' => 'nullable|array|max:5',
            'images.*' => 'nullable|string|max:2048',
            'is_active' => 'sometimes|boolean'
        ], [
            'title.unique' => 'Judul program sudah ada',
            'description.min' => 'Deskripsi minimal 10 karakter',
            'price.numeric' => 'Harga harus berupa angka',
            'price.min' => 'Harga tidak boleh negatif',
            'images.max' => 'Maksimal 5 gambar',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $program = Program::findOrFail($id);
            
            $updateData = $request->only(['title', 'description', 'price', 'is_active']);
            
            // Handle images
            if ($request->has('images')) {
                $images = [];
                if (is_array($request->images)) {
                    foreach ($request->images as $image) {
                        if (filter_var($image, FILTER_VALIDATE_URL)) {
                            $images[] = $image;
                        } else {
                            $images[] = $image;
                        }
                    }
                }
                $updateData['images'] = $images;
            }
            
            // Jika title berubah, generate slug baru
            if ($request->has('title') && $request->title !== $program->title) {
                $updateData['slug'] = Program::generateUniqueSlug($request->title, $id);
            }
            
            $program->update($updateData);
            $program = $program->fresh(); // Reload from database

            return response()->json([
                'status' => 'success',
                'message' => 'Program updated successfully',
                'data' => [
                    'id' => $program->id,
                    'title' => $program->title,
                    'description' => $program->description,
                    'slug' => $program->slug,
                    'price' => $program->price,
                    'formatted_price' => $program->formatted_price,
                    'is_free' => $program->is_free,
                    'is_active' => $program->is_active,
                    'images' => $program->image_urls,
                    'created_at' => $program->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $program->updated_at->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Program not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Update program error: ' . $e->getMessage(), [
                'program_id' => $id,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update program',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete program
     */
    public function destroy($id): JsonResponse
    {
        try {
            $program = Program::findOrFail($id);
            
            // Check if program has active enrollments
            $activeEnrollmentsCount = $program->activeEnrollments()->count();
            if ($activeEnrollmentsCount > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Cannot delete program with {$activeEnrollmentsCount} active enrollment(s)"
                ], 400);
            }
            
            // Delete associated images if stored locally
            if ($program->images && is_array($program->images)) {
                foreach ($program->images as $image) {
                    if (!filter_var($image, FILTER_VALIDATE_URL) && Storage::exists('public/' . $image)) {
                        Storage::delete('public/' . $image);
                    }
                }
            }
            
            $programTitle = $program->title;
            $program->delete();

            return response()->json([
                'status' => 'success',
                'message' => "Program '{$programTitle}' deleted successfully"
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Program not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Delete program error: ' . $e->getMessage(), [
                'program_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete program',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}