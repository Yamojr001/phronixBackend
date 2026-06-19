<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HistoryController extends Controller
{
    /**
     * Display a listing of the user's semesters (History).
     */
    public function index(Request $request)
    {
        $user = Auth::user() ?? $request->user();

        if (!$user) {
            return response()->json(['semesters' => []], 401);
        }

        // Fetch all semesters for the user, including the count of courses they contain
        $semesters = $user->semesters()->withCount('courses')->orderBy('created_at', 'desc')->get();

        if ($request->expectsJson()) {
            return response()->json([
                'semesters' => $semesters,
            ]);
        }

        return Inertia::render('History/Index', [
            'semesters' => $semesters,
        ]);
    }
}
