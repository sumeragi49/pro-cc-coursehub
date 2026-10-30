# CourseHub

オンライン学習プラットフォーム。

## 技術スタック

- PHP 8.2
- Laravel 10
- Laravel Sail（Docker 開発環境）
- MySQL 8.0
- Blade + Tailwind CSS

## 開発環境

Sail で起動:

```bash
./vendor/bin/sail up -d
```

マイグレーション:

```bash
./vendor/bin/sail artisan migrate
```

シーディング:

```bash
./vendor/bin/sail artisan db:seed
```

## テスト

```bash
./vendor/bin/sail artisan test
```

## ユーザーロール

- admin: 管理者
- coach: コーチ（コース作成）
- student: 受講生

## コース構造

Course > Chapter > Lesson > Quiz の階層構造。

- Course: コーチが作成するコース。Category・Tag を持つ
- Chapter: コース内の章（`order` で並び順を管理）
- Lesson: 章内のレッスン（公開 / 非公開）。受講生ごとの進捗は LessonProgress
- Quiz: レッスンに紐づく小テスト（Lesson と 1:1）。Question > Option、受講生の回答は Submission

受講登録は Enrollment（`EnrollmentService` が担当）。全レッスン完了時に `CourseCompleted` イベントが発火します。

## コーディング規約・設計方針

README.md の「コーディング規約」「設計方針」セクションを参照。
