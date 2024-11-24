<?php

namespace App\Http\Controllers\backend;

use App\Events\QuestionCreated;
use App\Http\Controllers\Controller;
use App\Mail\AnswerSubmittedMail;
use App\Models\Answer;
use App\Models\Question;
use App\Models\userAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OwnerController extends Controller
{
    // question create section
    public function questionCreate(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|min:10',
            'answer_type' => 'required|in:option,checkbox,short_answer',
        ]);

        $question = Question::create($validated);
        $question->owner_id = auth()->id();
        $question->save();

        broadcast(new QuestionCreated($question));

        return response()->json(['message' => 'Question Created Successfully'], 201);
    }
    public function questionUpdate(Request $request, $id)
    {
        $question = Question::findOrFail($id);
        $validated = $request->validate([
            'question' => 'required|string|min:10',
            'answer_type' => 'required|in:option,checkbox,short_answer',
        ]);
        $question->update($validated);
        return response()->json(['message' => 'Question Update Successfully'], 200);
    }
    public function questionDelete($id)
    {
        $question = Question::findOrFail($id);

        $question->delete();
        return response()->json(['message' => 'Question Delete Successfully'], 200);
    }

    // answer create section
    public function addAnswer(Request $request)
    {
        $validated = $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer' => 'required|string|max:255',
        ]);

        $currentAnswerCount = Answer::where('question_id', $validated['question_id'])->count();

        if ($currentAnswerCount >= 5) {
            return response()->json([
                'message' => 'A question can only have up to 5 answers.',
            ], 400);
        }
        $answer = Answer::create($validated);
        return response()->json(['message' => 'Answer added successfully.', 'data' => $answer], 201);
    }

    public function answerUpdate(Request $request, $id)
    {
        $answer = Answer::findOrFail($id);

        $validated = $request->validate([
            'answer' => 'required|string|max:255',
        ]);

        $answer->update($validated);
        return response()->json(['message' => 'Answer Update Successfully'], 200);
    }
    public function answerDelete($id)
    {
        $answer = Answer::findOrFail($id);
        $answer->delete();
        return response()->json(['message' => 'Answer Delete Successfully'], 201);
    }

    // answer submit by user
    public function submitAnswer(Request $request)
    {
        try {
            $validated = $request->validate([
                'questions' => 'required|array',
                'questions.*.question_id' => 'required|exists:questions,id',
                'questions.*.answer_ids' => 'nullable|array',
                'questions.*.answer_ids.*' => 'nullable|exists:answers,id',
                'questions.*.short_answer' => 'nullable|string',
            ]);

            $userAnswers = [];

            foreach ($validated['questions'] as $questionData) {
                $question = Question::findOrFail($questionData['question_id']);


                if ($question->status !== 'approved') {
                    return response()->json(['message' => 'You can only answer approved questions.'], 403);
                }

                if ($question->answer_type === 'short_answer' && empty($questionData['short_answer'])) {
                    return response()->json(['message' => 'Short answer is required for this question.'], 400);
                }

                if (($question->answer_type === 'option' || $question->answer_type === 'checkbox') && empty($questionData['answer_ids'])) {
                    return response()->json(['message' => 'Answer selection is required for this question.'], 400);
                }

                if ($question->answer_type === 'short_answer') {
                    $userAnswers[] = UserAnswer::create([
                        'user_id' => auth()->id(),
                        'question_id' => $questionData['question_id'],
                        'short_answer' => $questionData['short_answer'],
                    ]);
                } else {
                    foreach ($questionData['answer_ids'] as $answerId) {
                        $userAnswers[] = UserAnswer::create([
                            'user_id' => auth()->id(),
                            'question_id' => $questionData['question_id'],
                            'answer_id' => $answerId,
                        ]);
                    }
                }
            }

            $owner = $userAnswers[0]->question->owner;

            if (!$owner || !$owner->email) {
                throw new \Exception('Owner or owner email not found');
            }

            Mail::to($owner->email)->send(new AnswerSubmittedMail($userAnswers));

            return response()->json(['message' => 'Answers submitted successfully!'], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'trace' => $e->getTrace()], 500);
        }
    }

    //view answer
    public function viewSubmittedAnswers()
    {
        $ownerId = auth()->id();

        $questions = Question::with(['answers', 'submittedAnswers.user'])
            ->where('owner_id', $ownerId)
            ->get();

        return response()->json(['data' => $questions], 200);
    }

}
