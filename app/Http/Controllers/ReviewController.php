<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\Course;
use App\Models\Review;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Course $course)
    {
        $this->authorize('create', [Review::class, $course]);

        try {
            Review::create([
                'user_id' => auth()->id(),
                'course_id' => $course->id,
                'rating' => $request->validated('rating'),
                'comment' => $request->validated('comment'),
            ]);

            return redirect()->route('courses.show', $course)
                ->with('success', 'レビューを投稿しました。');
        } catch (\Exception $e) {
            report($e);

            return redirect()->route('courses.show', $course)
                ->with('error', 'レビューの投稿に失敗しました。');
        }
    }
}
