<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\User;
use App\Models\userAnswer;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function ownerCreate(Request $request)
    {
        $validator = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);
        $imagePaths = [];
        if ($request->has('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('img', 'public');
                $imagePaths[] = asset('storage/' . $path);
            }
        }
        $owner = User::create([
            'name' => $validator['name'],
            'images' => json_encode($imagePaths),
            'email' => $validator['email'],
            'role' => 'OWNER',
            'location' => $request->location ?? null,
            'password' => bcrypt($validator['password']),
        ]);
        $owner->save();

        return response()->json(['message' => 'Owner create successfully', 'owner' => $owner], 201);
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

        return response()->json(['notifications' => $notifications], 200);
    }

    // public function showUser()
    // {
    //     $users = User::all();
    //     $users = User::paginate(10);
    //     return response()->json(['data' => $users], 200);
    // }
    //show user and page
    public function showUser(Request $request)
    {
        $search = $request->input('search');

        $ownersQuery = User::where('role', 'OWNER');
        $usersQuery = User::where('role', 'USER');

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
        $owners = $ownersQuery->paginate(10, ['*'], 'owners_page');
        $users = $usersQuery->paginate(10, ['*'], 'users_page');

        $response = [];

        if ($owners->isEmpty()) {
            $response['owners_message'] = "There is no one by this name.";
        } else {
            $response['owners'] = $owners;
        }

        if ($users->isEmpty()) {
            $response['users_message'] = "There is no one by this name";
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

        return response()->json(['message' => 'Status updated successfully.',$question], 200);
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
    public function getMonthlyAnswerStatistics()
    {
        $monthlyStatistics = userAnswer::selectRaw('MONTH(created_at) as month, COUNT(*) as total_answers')
            ->groupBy('month')
            ->orderBy('month', )
            ->get()
            ->keyBy('month');

        $statistics = [];
        for ($i = 1; $i <= 12; $i++) {
            $statistics[] = [
                'month_name' => date('F', mktime(0, 0, 0, $i, 1)),
                'total_answers' => $monthlyStatistics->get($i)->total_answers ?? 0,
            ];
        }
        return $statistics;

    }

}
