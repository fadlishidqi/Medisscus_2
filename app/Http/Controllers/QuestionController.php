<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function index(Request $request, $question_bank_id)
    {
        $questions = Question::with(['answers:id,answer_text,question_id'])
            ->where('question_bank_id', $question_bank_id)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'question', 'question_image']);

        return response()->json([
            'meta' => [
                'status' => 'success',
                'message' => 'Questions retrieved successfully',
            ],
            'data' => $questions,
        ]);
    }

    public function getFromQuestionBanks(Request $request, $question_bank_id)
    {
        $query = Question::where('question_bank_id', $question_bank_id)
            ->select('id', 'question', 'created_at', 'updated_at');

        // query params to search by question
        if ($request->has('question') && $request->question !== '') {
            $query->where('question', 'like', '%' . $request->question . '%');
        }

        // asc order to get the latest questions first
        $query->orderBy('created_at', 'asc');

        // pagination
        $perPage = $request->get('per_page', 10);
        $sets = $query->paginate($perPage);

        return response()->json([
            'meta' => [
                'status' => 'success',
                'message' => 'Question retrieved successfully',
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
        $data = $request->validate([
            'question_bank_id' => 'required|uuid|exists:question_banks,id',
            'question' => 'required|string',
            'question_image' => 'nullable|image|max:2048',
            'explanation_text' => 'nullable|string',
            'explanation_image' => 'nullable|image|max:2048',
            'answers' => 'required|array|min:2',
            'answers.*.answer_text' => 'required|string',
            'answers.*.is_correct' => 'required|boolean',
        ]);

        DB::beginTransaction();

        try {
            // handle file uploads for question images
            if ($request->hasFile('question_image')) {
                $data['question_image'] = $request->file('question_image')->store('questions/image', 'public');
            }

            // handle file uploads for explanation images
            if ($request->hasFile('explanation_image')) {
                $data['explanation_image'] = $request->file('explanation_image')->store('questions/explanation', 'public');
            }

            $question = Question::create($data);

            foreach ($request->answers as $answer) {
                $question->answers()->create([
                    'question_id' => $question->id,
                    'answer_text' => $answer['answer_text'],
                    'is_correct' => $answer['is_correct'],
                ]);
            }

            DB::commit();
            $question->load('answers');

            return response()->json([
                'meta' => ['status' => 'success', 'message' => 'Question created successfully'],
                'data' => $question,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'meta' => ['status' => 'error', 'message' => $th->getMessage()],
            ], 500);
        }
    }

    public function show($id)
    {
        $question = Question::with([
            'answers:id,answer_text,is_correct,question_id'
        ])->findOrFail($id, [
            'id',
            'question',
            'question_image',
            'explanation_image',
            'explanation_text'
        ]);

        return response()->json([
            'meta' => [
                'status' => 'success',
                'message' => 'Question detail retrieved successfully',
            ],
            'data' => $question,
        ]);
    }

    public function update(Request $request, $id)
    {
        $question = Question::findOrFail($id);

        $data = $request->validate([
            'question' => 'sometimes|required|string',
            'question_image' => 'nullable|image|max:2048',
            'explanation_text' => 'nullable|string',
            'explanation_image' => 'nullable|image|max:2048',
            'answers' => 'nullable|array|min:2',
            'answers.*.answer_text' => 'required_with:answers|string',
            'answers.*.is_correct' => 'required_with:answers|boolean',
        ]);

        DB::beginTransaction();

        try {
            // handle question image
            if ($request->hasFile('question_image')) {
                $data['question_image'] = $request->file('question_image')->store('questions/image', 'public');
            }

            // handle explanation image
            if ($request->hasFile('explanation_image')) {
                $data['explanation_image'] = $request->file('explanation_image')->store('questions/explanation', 'public');
            }

            $question->update($data);

            // update answers if provided
            if (isset($data['answers'])) {
                $question->answers()->delete();

                foreach ($data['answers'] as $answer) {
                    $question->answers()->create([
                        'answer_text' => $answer['answer_text'],
                        'is_correct' => $answer['is_correct'],
                    ]);
                }
            }

            DB::commit();
            $question->load('answers');

            return response()->json([
                'meta' => ['status' => 'success', 'message' => 'Question updated successfully'],
                'data' => $question,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'meta' => ['status' => 'error', 'message' => $th->getMessage()],
            ], 500);
        }
    }

    public function destroy($id)
    {
        $question = Question::findOrFail($id);

        try {
            $question->answers()->delete();
            $question->delete();

            return response()->json([
                'meta' => ['status' => 'success', 'message' => 'Question deleted successfully'],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'meta' => ['status' => 'error', 'message' => $th->getMessage()],
            ], 500);
        }
    }
}
