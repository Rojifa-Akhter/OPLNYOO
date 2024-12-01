<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Mail\AnswerSubmittedMail;
use App\Models\privacy;
use App\Models\Question;
use App\Models\termsConditions;
use App\Models\User;
use App\Models\userAnswer;
use App\Notifications\AnswerSubmittedNotification;
use App\Notifications\NewQuestionNotification;
use App\Notifications\QuestionForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OwnerController extends Controller
{
    public function questionCreate(Request $request)
    {
        $validated = $request->validate([
            'questions' => 'required|array',
            'questions.*.question' => 'required|string',
            'questions.*.answer_type' => 'required|in:multiple,checkbox,short_answer',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*' => 'string',
        ]);

        $questions = [];
        foreach ($validated['questions'] as $questionData) {
            $options = null;
            if (in_array($questionData['answer_type'], ['multiple', 'checkbox']) && !empty($questionData['options'])) {
                $options = json_encode($questionData['options']);
            }

            $existingQuestion = Question::where('question', $questionData['question'])
                ->where('answer_type', $questionData['answer_type'])
                ->where('owner_id', auth()->id())
                ->first();

            if (!$existingQuestion) {
                $question = Question::updateOrCreate([
                    'question' => $questionData['question'],
                    'answer_type' => $questionData['answer_type'],
                    'options' => $options,
                    'owner_id' => auth()->id(),
                ]);

                // Notify the admin
                $admin = User::where('role', 'admin')->first();
                if ($admin) {
                    $admin->notify(new QuestionForm($question));
                }

                // Notify all users
                $users = User::where('role', 'USER')->get();
                foreach ($users as $user) {
                    $user->notify(new NewQuestionNotification($question));
                }
            } else {
                $question = $existingQuestion;
            }

            $questions[] = $question;
        }

        return response()->json([
            'message' => 'Questions Processed Successfully, notifications sent to admin and users.',
            'data' => $questions,
        ], 201);
    }

    public function questionDelete($id)
    {
        $question = Question::findOrFail($id);

        $question->delete();
        return response()->json(['message' => 'Question Delete Successfully'], 200);
    }
    //view answer
    public function viewSubmittedAnswers()
    {
        $ownerId = auth()->id();

        $questions = Question::with(['answers', 'submittedAnswers.user'])
            ->where('owner_id', $ownerId)
            ->get();

        return response()->json(
            ['question' => $questions], 200);
    }
    public function deleteSubmittedAnswers($id)
    {
        $ownerId = auth()->id();

        $answers = userAnswer::findOrFail($id);

        $answers->delete();
        return response()->json(["message" => "Submitted Answer Delete Successfully"]);
    }
    public function privacy(Request $request)
    {
        $owner_id = auth()->id();

        $privacy = privacy::create([
            'owner_id' => $owner_id,
            'title' => $request->title,
            'description' => $request->description,

        ]);

        return response()->json(['message' => $privacy], 201);
    }
    public function termsCondition(Request $request)
    {
        $owner_id = auth()->id();

        $termsCndition = termsConditions::create([
            'owner_id' => $owner_id,
            'title' => $request->title,
            'description' => $request->description,

        ]);

        return response()->json(['message' => $termsCndition], 201);
    }
    // user submitted answer get it owner
    public function getNotifications()
    {
        $owner = auth()->user();
        $notifications = $owner->notifications;
        $owner->unreadNotifications->markAsRead();

        return response()->json(['notifications' => 'Notifications marked as read.', $notifications], 200);
    }
    // submit answer for users
    public function submitAnswers(Request $request)
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.options' => 'nullable|array',
            'answers.*.short_answer' => 'nullable|string',
        ]);

        $userId = auth()->id();
        $submittedAnswers = [];

        foreach ($validated['answers'] as $answerData) {
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
        $ownerId = $question->owner_id; // Fetch the owner from the Question model
        $owner = User::find($ownerId);

        if ($owner) {

            Mail::to($owner->email)->send(new AnswerSubmittedMail(auth()->user(), $submittedAnswers));

            $owner->notify(new AnswerSubmittedNotification(auth()->user(), $submittedAnswers));
        }

        return response()->json([
            'message' => 'Answers Submitted Successfully',
            'data' => $submittedAnswers,
        ], 201);
    }
// owner create question
    public function getUserNotifications()
    {
        $user = auth()->user();
        $notifications = $user->notifications;
        $user->unreadNotifications->markAsRead();

        return response()->json(['notifications' => 'Notifications marked as read.', $notifications], 200);
    }
    public function survey()
    {
        $userId = auth()->id();

        $submittedAnswers = userAnswer::with(['question.user'])
            ->where('user_id', $userId)
            ->get();

        if ($submittedAnswers->isEmpty()) {
            return response()->json([
                'message' => 'You have not submitted any feedback yet.',
            ], 200);
        }

        $messages = $submittedAnswers->map(function ($answer) {
            $ownerName = $answer->question->user->name ?? 'Unknown Owner';
            $submittedAt = $answer->created_at->format('Y-m-d');

            return "You submitted your feedback to $ownerName on $submittedAt";
        });

        return response()->json([
            'messages' => $messages,
        ], 200);
    }
    public function companylist()
    {
        $owners = User::select('name', 'location')
            ->where('role', 'OWNER')
            ->paginate(10);
        if ($owners->isEmpty()) {
            return response()->json([
                'message' => 'No owners found.',
            ], 404);
        }

        return response()->json([
            'owner_list' => $owners,

        ], 200);
    }

    public function companyDetails($ownerId)
    {
        $owner = User::select('image', 'name', 'location', 'description')
            ->where('role', 'OWNER')
            ->where('id', $ownerId)
            ->first();

        if (!$owner) {
            return response()->json([
                'message' => 'Owner not found.',
            ], 404);
        }

        $image = $owner->image ?: 'https://t4.ftcdn.net/jpg/06/43/68/65/240_F_643686558_Efl6HB1ITw98bx1PdAd1wy56QpUTMh47.jpg';

        return response()->json([
            'ownerDetails' => [
                'image' => $image,
                'name' => $owner->name,
                'location' => $owner->location,
                'description' => $owner->description,
            ],
        ], 200);

    }

    public function privacyView()
{
    $privacy = Privacy::with('owner:id,name')->whereNotNull('owner_id')->get();

    $privacyWithOwnerName = $privacy->map(function ($item) {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'owner_name' => optional($item->owner)->name ?? 'Unknown Owner',
        ];
    });

    return response()->json(['message' => $privacyWithOwnerName], 200);
}

public function termsConditionView()
{
    $termsCondition = termsConditions::with('owner:id,name')->whereNotNull('owner_id')->get();

    $termsWithOwnerName = $termsCondition->map(function ($item) {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'owner_name' => optional($item->owner)->name ?? 'Unknown Owner',
        ];
    });

    return response()->json(['message' => $termsWithOwnerName], 200);
}


}
