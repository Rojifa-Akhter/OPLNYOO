<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\User;
use App\Models\userAnswer;
use App\Notifications\NewOwnerNotification;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function ownerCreate(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
        ]);

        $imagePath = null;
        if ($request->has('image')) {
            $image = $request->file('image');
            $path = $image->store('profile_images', 'public');
            $imagePath = asset('storage/' . $path);
        }

        $owner = User::create([
            'name' => $request->name,
            'image' => $imagePath,
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

        return response()->json(['message' => 'Owner created successfully', 'owner' => $owner], 201);
    }

    public function deleteUser(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully'], 200);
    }
    public function SoftDeletedUsers()
    {
        $deletedUsers = User::onlyTrashed()->get();

        return response()->json(['message' => $deletedUsers], 200);
    }
    //question form related
    public function getAdminNotifications()
    {
        $admin = auth()->user();
        $notifications = $admin->notifications;
        $admin->unreadNotifications->markAsRead();

        return response()->json(['notifications' => 'Notifications marked as read.', $notifications], 200);
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
        $defaultAvatar = 'https://img.freepik.com/free-vector/young-man-glasses-hoodie_1308-174658.jpg?ga=GA1.1.989225147.1732941118&semt=ais_hybrid';

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

        return response()->json($response, 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $question = Question::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:approved,cancelled',
        ]);

        $question->update(['status' => $validated['status']]);

        return response()->json(['message' => 'Status updated successfully.', $question], 200);
    }

    public function deleteQuestion($id)
    {
        $question = Question::findOrFail($id);
        $question->delete();

        return response()->json(['message' => 'Question deleted successfully.'], 200);
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

        return response()->json($statistics);
    }

}
