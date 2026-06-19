<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\University;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'phone_number' => 'nullable|string|max:20',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'school' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
        ]);

        $schoolName = trim((string) ($validated['school'] ?? ''));
        $departmentName = trim((string) ($validated['department'] ?? ''));

        if ($schoolName !== '') {
            $schoolName = University::firstOrCreate([
                'name' => $schoolName,
            ])->name;
        }

        if ($departmentName !== '') {
            $departmentName = Department::firstOrCreate([
                'name' => $departmentName,
            ])->name;
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? null,
            'password' => Hash::make($validated['password']),
            'school' => $schoolName !== '' ? $schoolName : null,
            'department' => $departmentName !== '' ? $departmentName : null,
        ]);

        event(new Registered($user));

        Auth::login($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }
}
