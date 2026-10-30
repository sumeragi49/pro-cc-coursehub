<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CoachCourseController extends Controller
{
    public function index()
    {
        $courses = Course::where('user_id', auth()->id())
            ->withCount('chapters', 'enrollments')
            ->latest()
            ->get();

        return view('coach.courses.index', compact('courses'));
    }

    public function dashboard()
    {
        $user = auth()->user();
        $courses = Course::where('user_id', $user->id)->get();
        $totalStudents = 0;
        foreach ($courses as $course) {
            $totalStudents += $course->enrollments()->where('status', 'active')->count();
        }

        return view('coach.dashboard', [
            'courseCount' => $courses->count(),
            'publishedCount' => $courses->where('status', 'published')->count(),
            'totalStudents' => $totalStudents,
        ]);
    }

    public function create()
    {
        $categories = Category::all();
        $tags = Tag::all();

        return view('coach.courses.create', compact('categories', 'tags'));
    }

    /**
     * コース新規作成処理
     *
     * スラッグ生成、画像アップロード、タグ同期、
     * 初期チャプター作成などを全て行う
     *
     * TODO: 画像処理をServiceに移動
     */
    public function store(StoreCourseRequest $request)
    {
        try {

            // ============================================
            // 1. バリデーション
            // ============================================

            // バリデーションは StoreCourseRequest で実施済み
            $validated = $request->validated();

            // ============================================
            // 2. スラッグ生成
            // ============================================

            $slug = $this->generateUniqueSlug($validated['title']);


            // ============================================
            // 3. 画像アップロード処理
            // ============================================

            $imagePath = null;

            // 画像がアップロードされた場合の処理
            if ($request->hasFile('image')) {
                $imagePath = $this->uploadImage($request->file('image'));

                // 保存に失敗した場合
                if (!$imagePath) {
                    return back()->withInput()->withErrors([
                        'image' => '画像のアップロードに失敗しました。',
                    ]);
                }
            }


            // ============================================
            // 4. Course レコード作成
            // ============================================

            // コースをデータベースに保存
            $course = Course::create([
                'user_id' => auth()->id(),
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'slug' => $slug,
                'description' => $validated['description'],
                'difficulty' => $validated['difficulty'],
                'image_path' => $imagePath,
                'status' => $validated['status'],
                'published_at' => null, // 後で設定する
            ]);


            // ============================================
            // 5. タグの同期（既存タグ + 新規タグ）
            // ============================================

            $this->syncTags($course, $validated['tags'] ?? [], $validated['new_tags'] ?? null);


            // ============================================
            // 6. 初期 Chapter の自動作成
            // ============================================

            // コース作成時に最初のチャプターを自動生成
            // これにより、コーチがすぐにレッスンを追加できる
            Chapter::create([
                'course_id' => $course->id,
                'title' => 'はじめに',
                'order' => 1,
            ]);


            // ============================================
            // 7. ステータスに応じた published_at の設定
            // ============================================

            // 公開ステータスの場合は公開日時を設定
            if ($validated['status'] === 'published') {
                $course->update([
                    'published_at' => now(),
                ]);
            }

            // 下書きの場合は published_at は null のまま
            // ※ archived は新規作成時には選択不可


            // ============================================
            // 8. リダイレクト
            // ============================================

            // コース一覧にリダイレクト（成功メッセージ付き）
            return redirect()->route('coach.courses.index')
                ->with('success', 'コースを作成しました。');

        } catch (\Exception $e) {

            // ============================================
            // 9. エラーハンドリング
            // ============================================

            // ログにエラーを記録
            \Log::error('コース作成エラー: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'request_data' => $request->except(['image']),
                'trace' => $e->getTraceAsString(),
            ]);

            // エラーメッセージを表示して入力フォームに戻す
            return back()->withInput()->withErrors([
                'error' => 'コースの作成中にエラーが発生しました。もう一度お試しください。',
            ]);
        }
    }

    /**
     * タイトルから重複しないスラッグを生成する
     */
    private function generateUniqueSlug(string $title): string
    {
        // タイトルからスラッグを生成
        $slug = Str::slug($title);

        // 空のスラッグ対策（日本語タイトルの場合）
        if (empty($slug)) {
            $slug = 'course-' . time();
        }

        // スラッグの重複チェック
        $originalSlug = $slug;
        $slugCount = 1;
        while (Course::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $slugCount;
            $slugCount++;
        }

        return $slug;
    }

    /**
     * コース画像を保存し、保存先のパスを返す（失敗時は false）
     */
    private function uploadImage(UploadedFile $image): string|false
    {
        // ファイル名を生成（ユニークにするため timestamp を付与）
        $fileName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();

        // storage/app/public/courses ディレクトリに保存
        return $image->storeAs('courses', $fileName, 'public');
    }

    /**
     * 既存タグと新規タグ（カンマ区切り）をコースに同期する
     *
     * @param  array<int>  $tagIds  既存タグのIDリスト
     */
    private function syncTags(Course $course, array $tagIds, ?string $newTags): void
    {
        // 新規タグが入力されている場合は作成して追加
        if (!empty($newTags)) {
            $newTagNames = array_map('trim', explode(',', $newTags));

            foreach ($newTagNames as $tagName) {
                if (empty($tagName)) {
                    continue;
                }

                // 既存のタグを検索、なければ新規作成
                $tag = Tag::firstOrCreate(
                    ['slug' => Str::slug($tagName)],
                    ['name' => $tagName]
                );

                // 重複しないようにIDを追加
                if (!in_array($tag->id, $tagIds)) {
                    $tagIds[] = $tag->id;
                }
            }
        }

        // pivot テーブルを同期
        if (!empty($tagIds)) {
            $course->tags()->sync($tagIds);
        }
    }

    public function edit(Course $course)
    {
        $this->authorize('update', $course);

        $categories = Category::all();
        $tags = Tag::all();
        $course->load('tags');

        return view('coach.courses.edit', compact('course', 'categories', 'tags'));
    }

    public function update(Request $request, Course $course)
    {
        $this->authorize('update', $course);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['required', 'string'],
            'difficulty' => ['required', 'in:beginner,intermediate,advanced'],
            'status' => ['required', 'in:draft,published,archived'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
        ]);

        $course->update([
            'title' => $validated['title'],
            'category_id' => $validated['category_id'],
            'description' => $validated['description'],
            'difficulty' => $validated['difficulty'],
            'status' => $validated['status'],
            'published_at' => $validated['status'] === 'published' && !$course->published_at ? now() : $course->published_at,
        ]);

        $course->tags()->sync($validated['tags'] ?? []);

        return redirect()->route('coach.courses.index')
            ->with('success', 'コースを更新しました。');
    }

    public function destroy(Course $course)
    {
        $this->authorize('delete', $course);

        $course->delete();

        return redirect()->route('coach.courses.index')
            ->with('success', 'コースを削除しました。');
    }
}
