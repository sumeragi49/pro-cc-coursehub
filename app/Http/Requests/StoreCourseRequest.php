<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:255',
                // 同じコーチ内でのタイトル重複を禁止
                Rule::unique('courses', 'title')->where('user_id', $this->user()->id),
            ],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['required', 'string'],
            'difficulty' => ['required', 'in:beginner,intermediate,advanced'],
            'status' => ['required', 'in:draft,published'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
            'new_tags' => ['nullable', 'string'], // カンマ区切りで新規タグを指定可能
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'コースタイトルは必須です。',
            'title.max' => 'コースタイトルは255文字以内で入力してください。',
            'title.unique' => '同じタイトルのコースが既に存在します。',
            'category_id.required' => 'カテゴリは必須です。',
            'category_id.exists' => '選択したカテゴリが存在しません。',
            'description.required' => 'コース説明は必須です。',
            'difficulty.required' => '難易度は必須です。',
            'difficulty.in' => '難易度は初級・中級・上級のいずれかを選択してください。',
            'status.required' => 'ステータスは必須です。',
            'status.in' => 'ステータスは下書きまたは公開のいずれかを選択してください。',
            'image.image' => 'アップロードできるのは画像ファイルのみです。',
            'image.mimes' => '画像の形式は jpeg、png、jpg、gif のいずれかにしてください。',
            'image.max' => '画像のサイズは2MB以内にしてください。',
            'tags.array' => 'タグの形式が正しくありません。',
            'tags.*.exists' => '選択したタグが存在しません。',
        ];
    }
}
