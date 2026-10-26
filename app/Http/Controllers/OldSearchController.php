<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Request;

/**
 * 旧検索コントローラー
 *
 * CourseController に統合済み。削除予定。
 * See: CourseController@index
 */
class OldSearchController extends Controller
{
    // 全文検索（旧実装）
    public function search(Request $request)
    {
        $keyword = $request->input('q', '');
        $categoryId = $request->input('category');
        $tagSlug = $request->input('tag');

        $query = Course::published();

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'LIKE', "%{$keyword}%")
                  ->orWhere('description', 'LIKE', "%{$keyword}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // タグでの絞り込み
        if ($tagSlug) {
            $tag = Tag::where('slug', $tagSlug)->first();
            if ($tag) {
                $query->whereHas('tags', function ($q) use ($tag) {
                    $q->where('tags.id', $tag->id);
                });
            }
        }

        $courses = $query->with(['user', 'category', 'tags'])
            ->orderBy('published_at', 'desc')
            ->paginate(12);

        $categories = Category::all();
        $tags = Tag::all();

        return view('courses.search', compact('courses', 'categories', 'tags', 'keyword'));
    }

    // autocomplete API（未完成）
    public function suggest(Request $request)
    {
        $keyword = $request->input('q', '');

        if (strlen($keyword) < 2) {
            return response()->json([]);
        }

        $courses = Course::published()
            ->where('title', 'LIKE', "%{$keyword}%")
            ->limit(5)
            ->pluck('title');

        return response()->json($courses);
    }
}
