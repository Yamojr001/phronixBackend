<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Quiz;

class QuizPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quiz $quiz): bool
    {
        // Anyone can view published quizzes
        if ($quiz->is_published) {
            return true;
        }

        // Only creator can view their own unpublished quizzes
        return $user->id === $quiz->creator_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Quiz $quiz): bool
    {
        // Only creator can update
        return $user->id === $quiz->creator_id;
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        // Only creator can delete
        return $user->id === $quiz->creator_id;
    }

    public function approve(User $user, Quiz $quiz): bool
    {
        // Only admins can approve
        return $user->is_admin;
    }
}
