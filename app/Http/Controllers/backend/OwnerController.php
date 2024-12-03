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
        $owner = auth()->user();

        foreach ($validated['questions'] as $questionData) {
            $options = null;
            if (in_array($questionData['answer_type'], ['multiple', 'checkbox']) && !empty($questionData['options'])) {
                $options = json_encode($questionData['options']);
            }

            $existingQuestion = Question::where('question', $questionData['question'])
                ->where('answer_type', $questionData['answer_type'])
                ->where('owner_id', $owner->id)
                ->first();

            if (!$existingQuestion) {
                $question = Question::updateOrCreate([
                    'question' => $questionData['question'],
                    'answer_type' => $questionData['answer_type'],
                    'options' => $options,
                    'owner_id' => $owner->id,
                ]);

                // Notify the admin
                $admin = User::where('role', 'admin')->first();
                if ($admin) {
                    $admin->notify(new QuestionForm($question));
                }

                // Notify all users with the owner's name
                $users = User::where('role', 'USER')->get();
                foreach ($users as $user) {
                    $user->notify(new NewQuestionNotification($question, $owner->name));
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
    public function about(Request $request)
    {
        $owner_id = auth()->id();

        $aboutUs = About::create([
            'owner_id' => $owner_id,
            'title' => $request->title,
            'description' => $request->description,

        ]);

        return response()->json(['message' => $aboutUs], 201);
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
        $ownerId = $question->owner_id;
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
    public function getUserNotifications()
    {
        $perPage = request()->query('per_page', 15);

        if ($perPage <= 0) {
            return response()->json([
                'message' => "'per_page' must be a positive number.",
            ], 400);
        }

        $owner = auth()->user();
        $notifications = $owner->notifications()->paginate($perPage);

        $formattedNotifications = collect($notifications->items())->map(function ($notification) {
            return [
                'id' => $notification->id, // Add notification ID
                'message' => $notification->data['message'] ?? 'No message available',
                'owner_id' => $notification->data['owner_id'] ?? null,
                'read_at' => $notification->read_at,
            ];
        });

        return response()->json([
            'notifications' => $formattedNotifications,
        ], 200);
    }

    public function markUserNotificationAsRead($id)
    {
        $owner = auth()->user();
        $notification = $owner->notifications()->find($id);

        if (!$notification) {
            return response()->json([
                'message' => 'Notification not found or does not belong to the user.',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read successfully.',
            'notification_id' => $id,
        ], 200);
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

        return response()->json(['data' => $messages], 200);
    }

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

        return response()->json(['message' => $aboutWithOwnerName], 200);
    }

//owner dashboard
    public function getOwnerStatistics()
    {
        // Fetch statistics
        $totalUsers = User::count();
        $totalSubmittedAnswers = userAnswer::count();

        // Count answers submitted today
        $totalAnswersToday = userAnswer::whereDate('created_at', now()->toDateString())->count();

        return response()->json([
            'total_users' => $totalUsers,
            'total_submitted_answers' => $totalSubmittedAnswers,
            'response_by_today' => $totalAnswersToday,
        ], 200);
    }
    public function viewFeedbackAnswers()
    {
        $perPage = request()->query('per_page', 15);

        if ($perPage <= 0) {
            return response()->json(['message' => "'per_page' must be a positive number."], 400);
        }
        $feedback = userAnswer::with('user:id,name,email,location')->select('id', 'user_id')->paginate($perPage);

        return response()->json(
            $feedback, 200);
    }
    public function getNotifications()
    {
        $owner = auth()->user();

        $notifications = $owner->notifications()->get()->map(function ($notification) {
            return [
                'id' => $notification->id,
                'data' => $notification->data,
                'read_at' => $notification->read_at,
            ];
        });

        return response()->json(['notifications' => $notifications], 200);
    }
    public function markNotificationAsRead($notificationId)
    {
        $owner = auth()->user();

        $notification = $owner->notifications()->find($notificationId);

        if (!$notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        if (!$notification->read_at) {
            $notification->markAsRead();
        }

        return response()->json(['message' => 'Notification marked as read.'], 200);
    }
    // view submitted answer and delete answer
    public function viewUserSubmittedAnswers()
    {
        $perPage = request()->query('per_page', 15);
        $date = request()->query('date');

        if ($perPage <= 0) {
            return response()->json([
                'message' => "'per_page' must be a positive number.",
            ], 400);
        }

        $query = userAnswer::with('question', 'user');

        if ($date) {
            $query->whereDate('created_at', '=', $date);
        }

        $submittedAnswers = $query->paginate($perPage);

        $formattedAnswers = $submittedAnswers->map(function ($answer) {

            $decodedOptions = null;
            if ($answer->question->answer_type == 'multiple' || $answer->question->answer_type == 'checkbox') {
                $decodedOptions = json_decode($answer->options);
            }

            return [
                'id' => $answer->id,
                'user_name' => $answer->user->name,
                'user_email' => $answer->user->email,
                'user_location' => $answer->user->location,
                'question' => $answer->question->question,
                'answer_type' => $answer->question->answer_type,
                'options' => $decodedOptions,
                'short_answer' => $answer->short_answer,
                'submitted_at' => $answer->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'data' => $formattedAnswers,
            'total_answers' => $submittedAnswers->total(),
            'pagination' => [
                'next_page_url' => $submittedAnswers->nextPageUrl(),
                'prev_page_url' => $submittedAnswers->previousPageUrl(),
            ],
        ], 200);
    }

    public function deleteSubmittedAnswers($id)
    {
        $ownerId = auth()->id();

        $answers = userAnswer::findOrFail($id);

        $answers->delete();
        return response()->json(["message" => "Submitted Answer Delete Successfully"]);
    }

}
