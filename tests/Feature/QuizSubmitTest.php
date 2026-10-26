<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizSubmitTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Course $course;
    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->student = User::factory()->create(['role' => 'student']);
        $this->course = Course::factory()->create();
        $chapter = Chapter::factory()->create(['course_id' => $this->course->id]);
        $lesson = Lesson::factory()->create(['chapter_id' => $chapter->id]);
        $this->quiz = Quiz::factory()->create(['lesson_id' => $lesson->id]);
    }

    /**
     * 問題を作成し、[問題, 正解の選択肢] の配列を返す
     */
    private function createQuestions(int $count): array
    {
        $questions = [];
        for ($i = 1; $i <= $count; $i++) {
            $question = Question::factory()->create(['quiz_id' => $this->quiz->id, 'order' => $i]);
            $correct = Option::factory()->correct()->create(['question_id' => $question->id]);
            Option::factory()->create(['question_id' => $question->id]);
            $questions[] = [$question, $correct];
        }

        return $questions;
    }

    private function submit(array $answers)
    {
        return $this->actingAs($this->student)
            ->post(route('courses.quizzes.submit', [$this->course, $this->quiz]), ['answers' => $answers]);
    }

    private function latestScore(): int
    {
        return Submission::where('user_id', $this->student->id)
            ->where('quiz_id', $this->quiz->id)
            ->latest('id')
            ->firstOrFail()
            ->score;
    }

    public function test_score_is_100_when_all_answers_are_correct(): void
    {
        $questions = $this->createQuestions(2);

        $answers = [];
        foreach ($questions as $index => [$question, $correct]) {
            $answers[$index] = ['question_id' => $question->id, 'option_id' => $correct->id];
        }

        $this->submit($answers)->assertRedirect();

        $this->assertSame(100, $this->latestScore());
    }

    public function test_score_is_calculated_when_some_questions_are_unanswered(): void
    {
        $questions = $this->createQuestions(2);

        // 1問目のみ正解、2問目は option_id なし（ラジオ未選択時の送信内容）
        $answers = [
            0 => ['question_id' => $questions[0][0]->id, 'option_id' => $questions[0][1]->id],
            1 => ['question_id' => $questions[1][0]->id],
        ];

        $this->submit($answers)->assertRedirect();

        $this->assertSame(50, $this->latestScore());
    }

    public function test_score_is_0_when_all_questions_are_unanswered(): void
    {
        $questions = $this->createQuestions(2);

        $answers = [
            0 => ['question_id' => $questions[0][0]->id],
            1 => ['question_id' => $questions[1][0]->id],
        ];

        $this->submit($answers)->assertRedirect();

        $this->assertSame(0, $this->latestScore());
    }

    public function test_quiz_without_questions_does_not_error_and_scores_0(): void
    {
        $this->submit([])->assertRedirect();

        $this->assertSame(0, $this->latestScore());
    }
}
