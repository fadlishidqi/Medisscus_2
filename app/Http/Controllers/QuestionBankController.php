<?php

namespace App\Http\Controllers;

use App\Models\QuestionBank;
use Illuminate\Http\Request;

class QuestionBankController extends Controller
{
    public function index(Request $request)
    {
        $query = QuestionBank::query();

        // query params to search by title
        if ($request->has('title') && $request->title !== '') {
            $query->where('title', 'like', '%' . $request->title . '%');
        }

        // pagination
        $perPage = $request->get('per_page', 10);
        $sets = $query->paginate($perPage);

        return response()->json([
            'meta' => [
                'status' => 'success',
                'message' => 'Question banks retrieved successfully',
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

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
        ]);

        $set = QuestionBank::create([
            'title' => $request->title,
        ]);

        return response()->json([
            'meta' => ['status' => 'success', 'message' => 'Question bank created successfully'],
            'data' => $set,
        ]);
    }

    public function show($id)
    {
        $set = QuestionBank::findOrFail($id);

        return response()->json([
            'meta' => ['status' => 'success', 'message' => 'Question bank detail retrieved successfully'],
            'data' => $set,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string',
        ]);

        $set = QuestionBank::findOrFail($id);
        $set->update([
            'title' => $request->title,
        ]);

        return response()->json([
            'meta' => ['status' => 'success', 'message' => 'Question bank updated successfully'],
            'data' => $set,
        ]);
    }

    public function destroy($id)
    {
        $set = QuestionBank::findOrFail($id);
        $set->delete();

        return response()->json([
            'meta' => ['status' => 'success', 'message' => 'Question bank deleted successfully'],
        ]);
    }
}
