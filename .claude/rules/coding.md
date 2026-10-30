---
description: コーディング規約
globs: ["app/**/*.php", "tests/**/*.php"]
---

- 変数名・メソッド名は camelCase、テーブル名・カラム名は snake_case
- モデルのリレーションは明示的に定義する
- Controller 名は単数形 + Controller（例: CourseController）

## バリデーション
- Controller ではバリデーションに Form Request を使用し、`app/Http/Requests/` に配置する
- 既存コードには `$request->validate()` の直書きが残っているが、新規・変更コードでは Form Request に従う（既存の一括置換はしない）

## 設計
- Controller は薄く保つ。複数モデルにまたがる処理・複雑なビジネスロジックは `app/Services/` の Service クラスに切り出す
- 既存の `CoachCourseController::store` のような Fat Controller は踏襲しない。新規コードは Service に切り出す
- リソースのアクセス制御は Policy で実装し、Controller で `$this->authorize()` を使用する
- 重要なドメインイベントは Event/Listener パターンで実装する

## エラーハンドリング
- Blade 画面はリダイレクトでエラーを返し、フラッシュメッセージで結果を通知する

## テスト
- 新機能には Feature テストを書く
- テストメソッド名は `test_` プレフィックス

詳細は README.md のコーディング規約・設計方針セクションを参照
