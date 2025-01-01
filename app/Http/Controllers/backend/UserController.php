<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Mail\AnswerSubmittedMail;
use App\Models\Question;
use App\Models\User;
use App\Models\userAnswer;
use App\Notifications\AnswerSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
        // submit answer for users
        public function submitAnswers(Request $request)
        {
            
            $validated = Validator::make($request->all(), [
                'answers' => 'required|array',
                'answers.*.question_id' => 'required|exists:questions,id',
                'answers.*.options' => 'nullable|array',
                'answers.*.short_answer' => 'nullable|string',
            ]);

            if ($validated->fails()) {
                return response()->json(['status' => false, 'message' => $validated->errors()]);
            }
            $userId = auth()->id();
            $submittedAnswers = [];

            foreach ($validated->validated()['questions'] as $answerData) {
                $question = Question::findOrFail($answerData['question_id']);
                if ($question->status !== 'approved') {
                    return response()->json([
                        'message' => 'You can only submit answers for approved questions.',
                    ], 400);
                }

                $answer = userAnswer::create([
                    'user_id' => $userId,
                    'question_id' => $answerData['question_id'],
                    'options' => isset($answerData['options']) ? json_encode($answerData['options']) : null,
                    'short_answer' => $answerData['short_answer'] ?? null,
                ]);

                $submittedAnswers[] = $answer;
            }

            // Notify the owner
            $ownerId = $question->owner_id;
            $owner = User::find($ownerId);

            if ($owner) {

                Mail::to($owner->email)->send(new AnswerSubmittedMail(auth()->user(), $submittedAnswers));

                $owner->notify(new AnswerSubmittedNotification(auth()->user(), $submittedAnswers));
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Answers Submitted Successfully',
                'data' => $submittedAnswers,
            ], 201);
        }
}
