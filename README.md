# CourseHub

オンライン学習プラットフォーム。コーチがコースを作成し、受講生が学習・進捗管理できるシステム。

## 環境構築

### 前提条件

- Docker Desktop がインストールされていること

### セットアップ手順

1. リポジトリをクローン

```bash
git clone https://github.com/coachtech-material/pro-cc-coursehub.git
cd pro-cc-coursehub
```

2. `.env` ファイルを作成

```bash
cp .env.example .env
```

3. `.env` の設定例

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

4. Docker を起動

```bash
docker compose up -d
```

5. マイグレーション・シーディング

```bash
./vendor/bin/sail artisan migrate --seed
```

6. アプリケーションキーの生成

```bash
php artisan key:generate
```

### キュー設定

本番環境では Redis を使用します。`.env` に以下を設定:

```
QUEUE_CONNECTION=redis
```

### アクセス

- アプリケーション: http://localhost
- phpMyAdmin: http://localhost:8080

## テスト

```bash
./vendor/bin/sail artisan test
```

## コーディング規約

### バリデーション
- Controller ではバリデーションに Form Request を使用する
- Form Request は `app/Http/Requests/` に配置する

### 命名規則
- 変数名・メソッド名は camelCase
- テーブル名・カラム名は snake_case
- Controller 名は単数形 + Controller（例: CourseController）

### エラーハンドリング
- Blade 画面はリダイレクトでエラーを返す
- フラッシュメッセージで結果を通知する

### テスト
- 新機能には Feature テストを書く
- テストメソッド名は `test_` プレフィックス

## 設計方針

### Controller
- Controller は薄く保つ。ビジネスロジックが複雑な場合は Service クラスに切り出す

### Service クラス
- 複数モデルにまたがる処理は Service クラスに集約する（例: EnrollmentService）
- `app/Services/` に配置する

### Policy
- リソースのアクセス制御は Policy で実装する
- Controller で `$this->authorize()` を使用する

### イベント
- 重要なドメインイベントは Event/Listener パターンで実装する

## 技術スタック

- PHP 8.2
- Laravel 10
- Laravel Sail（Docker 開発環境）
- MySQL 8.0
- Tailwind CSS
