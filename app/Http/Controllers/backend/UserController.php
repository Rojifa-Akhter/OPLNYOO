<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Mail\AnswerSubmittedMail;
use App\Models\About;
use App\Models\privacy;
use App\Models\Question;
use App\Models\termsConditions;
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

        foreach ($validated->validated()['answers'] as $answerData) {
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
    public function survey()
    {
        $userId = auth()->id();
        $perPage = request()->query('per_page', 15);

        if ($perPage <= 0) {
            return response()->json(['message' => "'per_page' must be a positive number."], 400);
        }

        $submittedAnswers = userAnswer::with(['question.owner'])
            ->where('user_id', $userId)
            ->paginate($perPage);

        if ($submittedAnswers->isEmpty()) {
            return response()->json(['message' => 'You have not submitted any feedback yet.'], 200);
        }

        $messages = $submittedAnswers->map(function ($answer) {
            $ownerName = $answer->question->owner->name ?? 'Unknown Owner';
            return "You submitted your feedback to $ownerName on " . $answer->created_at->format('Y-m-d');
        });

        return response()->json([
            'status' => 'success',
            'data' => $messages], 200);
    }
    //company list
    public function companylist()
    {
        $perPage = request()->query('per_page', 15);

        if ($perPage <= 0) {
            return response()->json([
                'message' => "'per_page' must be a positive number.",
            ], 400);
        }

        $owners = User::select('name', 'location')
            ->where('role', 'OWNER')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'owner_list' => $owners,
        ], 200);
    }
    public function companyDetails($ownerId)
    {
        $owner = User::select('id', 'image', 'name', 'location', 'description')
            ->where('role', 'OWNER')
            ->where('id', $ownerId)
            ->first();

        if (!$owner) {
            return response()->json([
                'message' => 'Owner not found.',
            ], 404);
        }

        // Use default image from the public folder if no image is found
        $image = $owner->image ? asset('storage/' . $owner->image) : asset('img/3.jpg');

        // Fetch submitted answers for this owner
        $userId = auth()->id();
        $submittedAnswers = userAnswer::with(['question'])
            ->where('user_id', $userId)
            ->whereHas('question', function ($query) use ($ownerId) {
                $query->where('owner_id', $ownerId);
            })
            ->get();

        $formattedAnswers = $submittedAnswers->map(function ($answer) {
            $decodedOptions = null;
            if (in_array($answer->question->answer_type, ['multiple', 'checkbox'])) {
                $decodedOptions = json_decode($answer->options);
            }

            return [
                'question' => $answer->question->question,
                'options' => $decodedOptions,
                'short_answer' => $answer->short_answer,
            ];
        });

        return response()->json([
            'status' => 'success',
            'companyDetails' => [
                'image' => $image,
                'name' => $owner->name,
                'location' => $owner->location,
                'description' => $owner->description,
            ],
            'submitted_answers' => $formattedAnswers,
        ], 200);
    }
    //view privacy
    public function privacyView()
    {
        $privacy = privacy::with('owner:id,name')->whereNotNull('owner_id')->get();

        $privacyWithOwnerName = $privacy->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'owner_name' => optional($item->owner)->name ?? 'Unknown Owner',
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => $privacyWithOwnerName], 200);
    }
    //terms and condition view
    //view privacy
    public function termsConditionView()
    {
        $terms = termsConditions::with('owner:id,name')->whereNotNull('owner_id')->get();

        $privacyWithOwnerName = $terms->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'owner_name' => optional($item->owner)->name ?? 'Unknown Owner',
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => $privacyWithOwnerName], 200);
    }
    //about view
    public function aboutView()
    {
        $about = About::with('owner:id,name')->whereNotNull('owner_id')->get();

        $aboutWithOwnerName = $about->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'owner_name' => optional($item->owner)->name ?? 'Unknown Owner',
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => $aboutWithOwnerName], 200);
    }

}
