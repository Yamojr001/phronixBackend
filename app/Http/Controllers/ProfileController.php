<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile information.
     */
    public function edit(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request)
    {
        $user = $request->user();
        $validatedData = $request->validated();

        if ($request->hasFile('avatar')) {
            // Delete old avatar if it exists
            if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
                \Storage::disk('public')->delete($user->avatar);
            }
            
            $file = $request->file('avatar');
            $path = $file->store('avatars', 'public');
            $validatedData['avatar'] = $path;
        } else {
            // Keep the old avatar if no new one was provided
            unset($validatedData['avatar']);
        }

        $user->fill($validatedData);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh(),
            'status' => 'profile-updated'
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // In API mode, we might not logout the same way as session
        // but we should revoke tokens if using Sanctum
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.'
        ]);
    }
}
