<?php

namespace App\Http\Controllers\backend;

use App\Models\User;
use App\Models\Question;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AdminController extends Controller
{
    public function ownerCreate(Request $request)
    {
        $validator = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);
        $owner = User::create([
            'name' => $validator['name'],
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
    public function reviewQuestions()
    {
        $questions = Question::where('status', 'pending')->with('owner')->get();

        return response()->json(['data' => $questions], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $question = Question::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:approved,cancelled',
        ]);

        $question->update(['status' => $validated['status']]);

        return response()->json(['message' => 'Status updated successfully.'], 200);
    }

    public function deleteQuestion($id)
    {
        $question = Question::findOrFail($id);
        $question->delete();

        return response()->json(['message' => 'Question deleted successfully.'], 200);
    }
}
