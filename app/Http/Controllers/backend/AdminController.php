<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\User;
use App\Models\userAnswer;
use App\Notifications\NewOwnerNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    //admin can create owner
    public function ownerCreate(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            // 'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
        ]);

        if ($validate->fails()) {
            return response()->json(['status' => false, 'message' => $validate->errors()]);
        }
        $path = null;
        if ($request->has('image')) {
            $image = $request->file('image');
            $path = $image->store('profile_images', 'public');

            // $imagePath = asset('storage/' . $path);
        }

        $owner = User::create([
            'name' => $request->name,
            'image' => $path,
            'email' => $request->email,
            'role' => 'OWNER',
            'location' => $request->location ?? null,
            'password' => bcrypt($request->password),
        ]);

        // Notify users
        $users = User::where('role', 'USER')->get();
        foreach ($users as $user) {
            $user->notify(new NewOwnerNotification($owner));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Owner created successfully', 'owner' => $owner], 201);
    }

    public function deleteUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()], 400);
        }

        $user = User::find($request->id);

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
        }

        $user->delete();

        return response()->json(['status' => 'success', 'message' => 'User deleted successfully'], 200);
    }

    public function SoftDeletedUsers()
    {
        $deletedUsers = User::onlyTrashed()->get();

        return response()->json([
            'status' => 'success',
            'message' => $deletedUsers], 200);
    }
    //question form notification
    public function getAdminNotifications()
    {
        $perPage = request()->query('per_page', 15);

        if ($perPage <= 0) {

            return response()->json([
                'message' => "'per_page' must be a positive number.",
            ], 400);
        }
        $user = Auth::user();

        if ($user->role !== 'ADMIN') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access. Only admins can view notifications.',
            ], 403);
        }

        $notifications = $user->notifications()->paginate($perPage);

        // Count unread notifications
        $unread = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();

        if ($notifications->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No notifications available.',
            ], 404);
        }

        $formattedNotifications = collect($notifications->items())->map(function ($notification) {
            return [
                'id' => $notification->id,
                'message' => $notification->data['message'] ?? 'No message available',
                'read_at' => $notification->read_at,
            ];
        });

        // Return the formatted notifications with unread count
        return response()->json([
            'status' => 'success',
            'unread_notification' => $unread,
            'notifications' => $formattedNotifications,
        ], 200);
    }

    public function markAdminNotificationAsRead($id)
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
            'status'=>'success',
            'message' => 'Notification marked as read successfully.',
            'notification_id' => $id,
        ], 200);
    }

    public function showUser(Request $request)
    {

        $perPage = request()->query('per_page', 15);

        if ($perPage <= 0) {
            return response()->json([
                'message' => "'per_page' must be a positive number.",
            ], 400);
        }
        $search = $request->input('search');
        $admin = auth()->user();

        $ownersQuery = User::where('role', 'OWNER')->where('id', '!=', $admin->id);
        $usersQuery = User::where('role', 'USER')->where('id', '!=', $admin->id);

        if ($search) {
            $ownersQuery->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });

            $usersQuery->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $owners = $ownersQuery->select('id', 'name', 'email', 'role', 'location', 'image', 'description')->paginate($perPage);
        $users = $usersQuery->select('id', 'name', 'email', 'role', 'location', 'image', 'description')->paginate($perPage);

        // Default avatar image URL
        $defaultAvatar = asset('img/3.jpg');


        $owners->getCollection()->transform(function ($owner) use ($defaultAvatar) {
            $owner->image = $owner->image ?: $defaultAvatar;
            return $owner;
        });

        $users->getCollection()->transform(function ($user) use ($defaultAvatar) {
            $user->image = $user->image ?: $defaultAvatar;
            return $user;
        });

        $response = [];

        if ($owners->isEmpty()) {
            $response['owners_message'] = "There is no one by this name.";
        } else {
            $response['owners'] = $owners;
        }

        if ($users->isEmpty()) {
            $response['users_message'] = "There is no one by this name.";
        } else {
            $response['users'] = $users;
        }

        return response()->json(
            ['status' => 'success',
            'message'=>$response], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $question = Question::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:approved,cancelled',
        ]);

        $question->update(['status' => $validated['status']]);

        return response()->json([
            'status'=>'success',
            'message' => 'Status updated successfully.', $question], 200);
    }

    public function deleteQuestion($id)
    {
        $question = Question::findOrFail($id);
        $question->delete();

        return response()->json([
            'status'=>'success',
            'message' => 'Question deleted successfully.'], 200);
    }

    // dashboard
    public function getDashboardStatistics()
    {

        $totalUsers = User::count();

        $totalQuestions = Question::count();

        $totalAnswers = userAnswer::count();

        return response()->json([
            'total_users' => $totalUsers,
            'total_questions' => $totalQuestions,
            'total_answers' => $totalAnswers,
        ], 200);
    }

    public function getMonthlyAnswerStatistics(Request $request)
    {
        $year = $request->query('year', date('Y'));

        $monthlyStatistics = userAnswer::selectRaw('MONTH(created_at) as month, COUNT(*) as total_answers')
            ->whereYear('created_at', $year) // Filter year
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $statistics = [];
        for ($i = 1; $i <= 12; $i++) {
            $statistics[] = [
                'month_name' => date('F', mktime(0, 0, 0, $i, 1)),
                'total_answers' => $monthlyStatistics->get($i)->total_answers ?? 0,
            ];
        }

        return response()->json(
            ['status'=>'success',
            'message' =>$statistics],200);
    }

}
