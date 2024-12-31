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
use Illuminate\Support\Facades\Validator;

class OwnerController extends Controller
{
    public function questionCreate(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'questions' => 'required|array',
            'questions.*.question' => 'required|string',
            'questions.*.answer_type' => 'required|in:multiple,checkbox,short_answer',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*' => 'string',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validated->errors(),
            ], 422);
        }

        $questions = [];
        $owner = auth()->user();

        foreach ($validated->validated()['questions'] as $questionData) {
            $options = null;

            if (in_array($questionData['answer_type'], ['multiple', 'checkbox']) && !empty($questionData['options'])) {
                $options = json_encode($questionData['options']);
            }

            $question = Question::updateOrCreate(
                [
                    'question' => $questionData['question'],
                    'answer_type' => $questionData['answer_type'],
                    'owner_id' => $owner->id,
                ],
                [
                    'options' => $options,
                ]
            );

            // Notify the admin
            $admin = User::where('role', 'admin')->first();
            if ($admin) {
                $admin->notify(new QuestionForm($question));
            }

            // Notify all users
            $users = User::where('role', 'USER')->get();
            foreach ($users as $user) {
                $user->notify(new NewQuestionNotification($question, $owner->name));
            }

            $questions[] = $question;
        }

        return response()->json([
            'status'=> 'success',
            'message' => 'Questions processed successfully, notifications sent to admin and users.',
            'data' => $questions,
        ], 201);
    }

    public function questionDelete($id)
    {
        $question = Question::findOrFail($id);

        $question->delete();
        return response()->json([
            'status'=>'success',
            'message' => 'Question Delete Successfully'], 200);
    }

    public function privacy(Request $request)
    {
        $owner_id = auth()->id();

        if (!$owner_id) {
            return response()->json(['status' => false, 'message' => 'Owner Not Found'], 404);
        }
        $privacy = privacy::create([
            'owner_id' => $owner_id,
            'title' => $request->title,
            'description' => $request->description,

        ]);

        return response()->json([
            'status'=>'success',
            'message' => $privacy], 201);
    }
    public function termsCondition(Request $request)
    {
        $owner_id = auth()->id();

        $termsCndition = termsConditions::create([
            'owner_id' => $owner_id,
            'title' => $request->title,
            'description' => $request->description,

        ]);

        return response()->json([
            'status'=>'success',
            'message' => $termsCndition], 201);
    }

    //about add
    public function about(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'images' => 'nullable|array|max:3',
        ]);

        $about = About::first();

        $newImages = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('about_images', 'public');
                $newImages[] = asset('storage/' . $path);
            }
        }

        if ($about) {
            // Get existing images (if any)
            $existingImages = json_decode($about->image, true) ?: [];

            // Merge new images with existing images, but ensure there are no more than 3 images
            $allImages = array_merge($existingImages, $newImages);
            if (count($allImages) > 3) {
                $allImages = array_slice($allImages, 0, 3);
            }

            $about->update([
                'title' => $request->title,
                'description' => $request->description,
                'image' => json_encode($allImages),
            ]);
        } else {
            $about = About::create([
                'title' => $request->title,
                'description' => $request->description,
                'image' => json_encode($newImages),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => $about->wasRecentlyCreated ? 'About created successfully' : 'About updated successfully',
            'about' => $about,
        ], 200);
    }


    // submit answer for users
    public function submitAnswers(Request $request)
    {
        $validated = Validator::make($request->all(),[
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.options' => 'nullable|array',
            'answers.*.short_answer' => 'nullable|string',
        ]);

        if ($validated->fails()) {
            return response()->json(['status'=> false, 'message'=>$validated->errors()]);
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
            'status' => 'success',
            'notifications' => $formattedNotifications,
        ], 200);
    }

    public function markUserNotificationAsRead($id)
    {
        $owner = auth()->user();
        $notification = $owner->notifications()->find($id);

        if (!$notification) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found or does not belong to the user.',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'status'=> 'success',
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

        return response()->json([
            'status'=> 'success',
            'data' => $messages], 200);
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
            'status'=> 'success',
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

        return response()->json([
            'status'=>'success',
            'message' => $privacyWithOwnerName], 200);
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

        return response()->json([
            'status'=>'success',
            'message' => $termsWithOwnerName], 200);
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

        return response()->json([
            'status'=>'success',
            'message' => $aboutWithOwnerName], 200);
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
            'status'=>'success',
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
            ['status'=>'success',$feedback]
            , 200);
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

        return response()->json([
            'status'=>'success',
            'notifications' => $notifications], 200);
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

        return response()->json([
            'status'=>'success',
            'message' => 'Notification marked as read.'], 200);
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
            'status'=>'success',
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
        return response()->json([
            'status'=>'success',"message" => "Submitted Answer Delete Successfully"]);
    }


}
