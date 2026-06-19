<?php

namespace App\Http\Controllers;

use App\Models\University;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UniversityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $universities = University::query()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where('name', 'like', '%' . $query . '%');
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name']);

        return response()->json([
            'data' => $universities,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:universities,name',
        ]);

        $university = University::create([
            'name' => trim($validated['name']),
        ]);

        return response()->json([
            'message' => 'University added successfully.',
            'data' => $university,
        ], 201);
    }
}
