<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Requests\Api\SemesterRequest;

class SemesterController extends Controller
{
    /**
     * Create a new semester for the user.
     */
    public function store(SemesterRequest $request)
    {
        $user = Auth::user();

        $semester = $user->semesters()->create([
            'name' => $request->name,
        ]);

        // Automatically switch the user to the newly created semester
        $user->update(['current_semester_id' => $semester->id]);

        return response()->json([
            'message' => 'Semester created and activated successfully!',
            'semester' => $semester,
            'user' => $user->fresh()
        ], 201);
    }

    /**
     * Switch the user's active semester.
     */
    public function switch(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $user = Auth::user();

        // Ensure the semester actually belongs to the user
        $semester = $user->semesters()->where('id', $validated['semester_id'])->firstOrFail();

        $user->update(['current_semester_id' => $semester->id]);

        return response()->json([
            'message' => 'Switched to ' . $semester->name,
            'semester_id' => $semester->id,
            'user' => $user->fresh()
        ]);
    }
}
