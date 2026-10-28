<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coach = User::factory()->create(['role' => 'coach']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => Category::factory()->create()->id,
            'status' => 'published',
        ]);
    }

    public function test_completed_student_can_post_review(): void
    {
        Enrollment::factory()->completed()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);

        $response = $this->actingAs($this->student)
            ->post("/courses/{$this->course->id}/reviews", [
                'rating' => 5,
                'comment' => '分かりやすい内容でした。',
            ]);

        $response->assertRedirect("/courses/{$this->course->id}");
        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'rating' => 5,
            'comment' => '分かりやすい内容でした。',
        ]);
    }

    public function test_active_student_cannot_post_review(): void
    {
        Enrollment::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);

        $response = $this->actingAs($this->student)
            ->post("/courses/{$this->course->id}/reviews", ['rating' => 4]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_coach_cannot_post_review(): void
    {
        $response = $this->actingAs($this->coach)
            ->post("/courses/{$this->course->id}/reviews", ['rating' => 4]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_student_cannot_post_second_review_for_same_course(): void
    {
        Enrollment::factory()->completed()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);
        Review::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'rating' => 5,
        ]);

        $response = $this->actingAs($this->student)
            ->post("/courses/{$this->course->id}/reviews", ['rating' => 1]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('reviews', 1);
        $this->assertDatabaseHas('reviews', ['rating' => 5]);
    }

    public function test_rating_out_of_range_is_rejected(): void
    {
        Enrollment::factory()->completed()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);

        foreach ([0, 6] as $rating) {
            $response = $this->actingAs($this->student)
                ->post("/courses/{$this->course->id}/reviews", ['rating' => $rating]);

            $response->assertSessionHasErrors('rating');
        }

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_review_can_be_posted_without_comment(): void
    {
        Enrollment::factory()->completed()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);

        $response = $this->actingAs($this->student)
            ->post("/courses/{$this->course->id}/reviews", ['rating' => 3]);

        $response->assertRedirect("/courses/{$this->course->id}");
        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'rating' => 3,
            'comment' => null,
        ]);
    }

    public function test_reviews_are_listed_on_course_page(): void
    {
        Review::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'rating' => 4,
            'comment' => '一覧表示の確認用コメント',
        ]);

        $response = $this->actingAs($this->coach)->get("/courses/{$this->course->id}");

        $response->assertStatus(200);
        $response->assertSee('一覧表示の確認用コメント');
        $response->assertSee($this->student->name);
    }
}
