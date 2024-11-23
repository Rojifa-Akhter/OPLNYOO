<?php

namespace App\Http\Controllers\backend;

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
        ]);

        $question = Question::create($validated);
        $question->owner_id = auth()->id();
        $question->save();
        return response()->json(['message' => 'Question Create Successfully'], 201);
    }
    public function questionUpdate(Request $request, $id)
    {
        $question = Question::findOrFail($id);
        $validated = $request->validate([
            'question' => 'required|string|min:10',
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
    public function answerCreate(Request $request)
    {
        $validated = $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer' => 'required|string|max:255',
        ]);
        $answerCount = Answer::where('question_id', $validated['question_id'])->count();

        if ($answerCount >= 4) {
            return response()->json(['message' => 'This question already has the maximum number of answers (4).'], 400);
        }
        $answer = Answer::create($validated);
        $answer->save();

        return response()->json(['message' => 'Answer Created Successfully'], 201);
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
                'question_id' => 'required|exists:questions,id',
                'answer_id' => 'required|exists:answers,id',
            ]);

            $user_id = auth()->id();

            $answer = Answer::findOrFail($validated['answer_id']);
            if ($answer->question_id != $validated['question_id']) {
                return response()->json(['message' => 'Answer does not belong to the specified question.'], 400);
            }

            $userAnswer = UserAnswer::create([
                'user_id' => $user_id,
                'question_id' => $validated['question_id'],
                'answer_id' => $validated['answer_id'],
            ]);

            // Debug relationships
            $question = $userAnswer->question;
            $owner = $question->owner;

            if (!$owner || !$owner->email) {
                throw new \Exception('Owner or owner email not found');
            }

            // Send email to the question owner
            Mail::to($owner->email)->send(new AnswerSubmittedMail($userAnswer));

            return response()->json(['message' => 'Answer submitted successfully!'], 201);
        }
         catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
         catch (\Exception $e) {
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
