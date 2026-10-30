<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function create(User $user, Course $course): bool
    {
        if ($user->role !== 'student') {
            return false;
        }

        // 受講完了していること
        $hasCompleted = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->exists();

        if (! $hasCompleted) {
            return false;
        }

        // 1コースにつき1人1件
        return ! Review::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->exists();
    }
}
