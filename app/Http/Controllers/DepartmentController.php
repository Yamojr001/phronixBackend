<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $departments = Department::query()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where('name', 'like', '%' . $query . '%');
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name']);

        return response()->json([
            'data' => $departments,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
        ]);

        $department = Department::create([
            'name' => trim($validated['name']),
        ]);

        return response()->json([
            'message' => 'Department added successfully.',
            'data' => $department,
        ], 201);
    }
}
