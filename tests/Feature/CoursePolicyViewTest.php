<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoursePolicyViewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $coach;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->coach = User::factory()->create(['role' => 'coach']);
        $this->student = User::factory()->create(['role' => 'student']);
    }

    public function test_admin_can_view_draft_course(): void
    {
        $course = Course::factory()->draft()->create(['user_id' => $this->coach->id]);

        $this->actingAs($this->admin)
            ->get(route('courses.show', $course))
            ->assertOk();
    }

    public function test_coach_can_view_own_draft_course(): void
    {
        $course = Course::factory()->draft()->create(['user_id' => $this->coach->id]);

        $this->actingAs($this->coach)
            ->get(route('courses.show', $course))
            ->assertOk();
    }

    public function test_student_can_view_published_course(): void
    {
        $course = Course::factory()->published()->create(['user_id' => $this->coach->id]);

        $this->actingAs($this->student)
            ->get(route('courses.show', $course))
            ->assertOk();
    }

    public function test_student_cannot_view_draft_course(): void
    {
        $course = Course::factory()->draft()->create(['user_id' => $this->coach->id]);

        $this->actingAs($this->student)
            ->get(route('courses.show', $course))
            ->assertForbidden();
    }

    public function test_student_cannot_view_archived_course(): void
    {
        $course = Course::factory()->archived()->create(['user_id' => $this->coach->id]);

        $this->actingAs($this->student)
            ->get(route('courses.show', $course))
            ->assertForbidden();
    }
}
