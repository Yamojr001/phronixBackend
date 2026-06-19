<?php

namespace App\Http\Controllers;

use App\Models\SemesterOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SemesterOptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $options = SemesterOption::query()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where('name', 'like', '%' . $query . '%');
            })
            ->orderBy('level')
            ->orderBy('spill')
            ->limit(50)
            ->get(['id', 'level', 'spill', 'name']);

        return response()->json([
            'data' => $options,
        ]);
    }
}
