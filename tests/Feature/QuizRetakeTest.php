<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizRetakeTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Course $course;
    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $coach = User::factory()->create(['role' => 'coach']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->course = Course::factory()->create([
            'user_id' => $coach->id,
            'category_id' => Category::factory()->create()->id,
            'status' => 'published',
        ]);
        Enrollment::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);
        $chapter = Chapter::factory()->create(['course_id' => $this->course->id]);
        $lesson = Lesson::factory()->create(['chapter_id' => $chapter->id]);
        $this->quiz = Quiz::factory()->create(['lesson_id' => $lesson->id, 'passing_score' => 70]);
    }

    private function createSubmission(int $score): Submission
    {
        return Submission::factory()->create([
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => $score,
        ]);
    }

    /**
     * 問題を1つ作成し、正解の選択肢と不正解の選択肢を返す。
     *
     * @return array{0: Question, 1: Option, 2: Option}
     */
    private function createQuestionWithOptions(): array
    {
        $question = Question::factory()->create(['quiz_id' => $this->quiz->id]);
        $correct = Option::factory()->correct()->create(['question_id' => $question->id]);
        $wrong = Option::factory()->create(['question_id' => $question->id]);

        return [$question, $correct, $wrong];
    }

    public function test_student_can_submit_quiz_for_the_first_time(): void
    {
        [$question, $correct] = $this->createQuestionWithOptions();

        $response = $this->actingAs($this->student)
            ->post(route('courses.quizzes.submit', [$this->course, $this->quiz]), [
                'answers' => [['question_id' => $question->id, 'option_id' => $correct->id]],
            ]);

        $response->assertRedirect(route('courses.quizzes.result', [$this->course, $this->quiz]));
        $this->assertDatabaseCount('submissions', 1);
        $this->assertDatabaseHas('submissions', [
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 100,
        ]);
    }

    public function test_student_can_resubmit_quiz_after_failing(): void
    {
        [$question, $correct] = $this->createQuestionWithOptions();
        $this->createSubmission(0);

        $response = $this->actingAs($this->student)
            ->post(route('courses.quizzes.submit', [$this->course, $this->quiz]), [
                'answers' => [['question_id' => $question->id, 'option_id' => $correct->id]],
            ]);

        $response->assertRedirect(route('courses.quizzes.result', [$this->course, $this->quiz]));
        $this->assertDatabaseCount('submissions', 2);
        $this->assertDatabaseHas('submissions', [
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 100,
        ]);
    }

    public function test_student_can_retake_after_failing(): void
    {
        $this->createSubmission(40);

        $this->actingAs($this->student)
            ->get(route('courses.quizzes.show', [$this->course, $this->quiz]))
            ->assertStatus(200);
    }

    public function test_student_is_redirected_to_result_when_opening_passed_quiz(): void
    {
        $this->createSubmission(40);
        $this->createSubmission(70);

        $this->actingAs($this->student)
            ->get(route('courses.quizzes.show', [$this->course, $this->quiz]))
            ->assertRedirect(route('courses.quizzes.result', [$this->course, $this->quiz]))
            ->assertSessionHasErrors('quiz');
    }

    public function test_student_cannot_submit_after_passing(): void
    {
        $this->createSubmission(40);
        $this->createSubmission(70);

        $this->actingAs($this->student)
            ->post(route('courses.quizzes.submit', [$this->course, $this->quiz]), ['answers' => []])
            ->assertStatus(403);
    }

    public function test_result_shows_history_and_retake_button_when_failed(): void
    {
        $this->createSubmission(30);
        $this->createSubmission(50);

        $response = $this->actingAs($this->student)
            ->get(route('courses.quizzes.result', [$this->course, $this->quiz]));

        $response->assertStatus(200);
        $response->assertSee('受験履歴');
        $response->assertSee('30%');
        $response->assertSee('50%');
        $response->assertSee('再受験する');
        $response->assertDontSee('合格済みのため再受験できません。');
    }

    public function test_result_lists_submissions_from_newest_to_oldest(): void
    {
        Submission::factory()->create([
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 20,
            'submitted_at' => now()->subDays(2),
        ]);
        Submission::factory()->create([
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 30,
            'submitted_at' => now(),
        ]);
        Submission::factory()->create([
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => 10,
            'submitted_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($this->student)
            ->get(route('courses.quizzes.result', [$this->course, $this->quiz]));

        $response->assertStatus(200);
        $this->assertSame([30, 20, 10], $response->viewData('submissions')->pluck('score')->all());
    }

    public function test_result_hides_retake_button_when_passed(): void
    {
        $this->createSubmission(30);
        $this->createSubmission(90);

        $response = $this->actingAs($this->student)
            ->get(route('courses.quizzes.result', [$this->course, $this->quiz]));

        $response->assertStatus(200);
        $response->assertSee('受験履歴');
        $response->assertDontSee('再受験する');
        $response->assertSee('合格済みのため再受験できません。');
    }

    public function test_unenrolled_student_cannot_open_or_submit_quiz(): void
    {
        $other = User::factory()->create(['role' => 'student']);

        $this->actingAs($other)
            ->get(route('courses.quizzes.show', [$this->course, $this->quiz]))
            ->assertStatus(403);

        $this->actingAs($other)
            ->post(route('courses.quizzes.submit', [$this->course, $this->quiz]), ['answers' => []])
            ->assertStatus(403);
    }

    public function test_coach_can_view_quiz(): void
    {
        $this->actingAs($this->course->user)
            ->get(route('courses.quizzes.show', [$this->course, $this->quiz]))
            ->assertStatus(200);
    }
}
