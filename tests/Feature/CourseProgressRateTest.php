<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseProgressRateTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Course $course;
    private Chapter $chapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['role' => 'student']);
        $this->course = Course::factory()->create();
        $this->chapter = Chapter::factory()->create(['course_id' => $this->course->id]);
    }

    private function completeLesson(Lesson $lesson): void
    {
        LessonProgress::factory()->create([
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
        ]);
    }

    public function test_progress_is_100_when_all_published_lessons_completed_with_unpublished_lessons(): void
    {
        $publishedLessons = Lesson::factory()->count(4)->create(['chapter_id' => $this->chapter->id]);
        Lesson::factory()->unpublished()->create(['chapter_id' => $this->chapter->id]);

        foreach ($publishedLessons as $lesson) {
            $this->completeLesson($lesson);
        }

        $this->assertSame(100, $this->course->getProgressRate($this->student->id));
    }

    public function test_progress_rate_is_correct_when_partially_completed(): void
    {
        $publishedLessons = Lesson::factory()->count(4)->create(['chapter_id' => $this->chapter->id]);
        Lesson::factory()->unpublished()->create(['chapter_id' => $this->chapter->id]);

        $this->completeLesson($publishedLessons[0]);

        // 公開4件中1件完了 = 25%（非公開レッスンは分母に含めない）
        $this->assertSame(25, $this->course->getProgressRate($this->student->id));
    }

    public function test_progress_is_0_when_course_has_no_lessons(): void
    {
        $this->assertSame(0, $this->course->getProgressRate($this->student->id));
    }
}
