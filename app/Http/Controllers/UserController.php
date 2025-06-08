<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        // query params to search by name
        if ($request->has('name') && $request->name !== '') {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // pagination
        $perPage = $request->get('per_page', 10);
        $sets = $query->paginate($perPage);

        return response()->json([
            'meta' => [
                'status' => 'success',
                'message' => 'Users retrieved successfully',
            ],
            'data' => $sets->items(),
            'pagination' => [
                'current_page' => $sets->currentPage(),
                'per_page' => $sets->perPage(),
                'total' => $sets->total(),
                'last_page' => $sets->lastPage(),
            ]
        ]);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);

        return response()->json([
            'meta' => [
                'status' => 'success',
                'message' => 'User detail retrieved successfully',
            ],
            'data' => $user,
        ]);
    }
}
