<?php

namespace App\Http\Controllers;

use App\Models\Tryout;
use Illuminate\Http\Request;

class TryoutController extends Controller
{
    public function index(Request $request, $program_id)
    {
        $data = Tryout::where('program_id', $program_id)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'title', 'minute_limit', 'hash', 'created_at', 'updated_at']);

        return response()->json([
            'meta' => [
                'status' => 'success',
                'message' => 'Tryouts retrieved successfully',
            ],
            'data' => $data,
        ]);
    }

    public function getAllTryouts(Request $request)
    {
        $query = Tryout::query();

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
                'message' => 'Tryout retrieved successfully',
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

    public function create(Request $request)
    {
        $request->validate([
            'question_bank_id' => 'required|uuid|exists:question_banks,id',
            'program_id' => 'required|uuid|exists:programs,id',
            'title' => 'required|string|max:255',
            'minute_limit' => 'required|integer|min:1',
            'hash' => 'nullable|string|max:255',
        ]);

        $tryout = Tryout::create([
            'question_bank_id' => $request->question_bank_id,
            'program_id' => $request->program_id,
            'title' => $request->title,
            'minute_limit' => $request->minute_limit,
        ]);

        return response()->json([
            'meta' => [
                'status' => 'success',
                'message' => 'Tryout created successfully',
            ],
            'data' => $tryout,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $tryout = Tryout::select('id', 'title', 'minute_limit', 'created_at', 'updated_at')
            ->findOrFail($id);

        return response()->json([
            'meta' => [
                'status' => 'success',
                'message' => 'Tryout detail retrieved successfully',
            ],
            'data' => $tryout,
        ]);
    }

    public function getQuestion(Request $request, $id)
    {
        $tryout = Tryout::with([
            'questionBank.questions.answers' => function ($query) {
                $query->select('id', 'question_id', 'answer_text');
            }
        ])->findOrFail($id);

        $questions = $tryout->questionBank->questions
            ->sortBy('created_at')
            ->map(function ($question) {
                return [
                    'id' => $question->id,
                    'question' => $question->question,
                    'options' => $question->answers->map(function ($answer) {
                        return [
                            'id' => $answer->id,
                            'answer_text' => $answer->answer_text,
                        ];
                    })->values()->all(),
                ];
            })->values()->all();

        return response()->json([
            'meta' => [
                'status' => 'success',
                'message' => 'Tryout questions retrieved successfully',
            ],
            'data' => $questions,
        ]);
    }
}
