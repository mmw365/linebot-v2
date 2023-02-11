## このアプリケーションについて

LINEのチャットボットです。
※現在はカイモノリストを実装中

## 開発環境

以下の開発環境で動作確認しています
- Windows 11
- Docker Desktop
- WSL2
- Ubuntu 22.04.01 LST (Composer インストール済み)

## 開発環境の設定

Ubuntu 環境で下のコマンドの実行
- git clone https://github.com/mmw365/linebot-v2.git
- cd linebot-v2
- composer install

### 開発環境の起動

- ./vendor/bin/sail up -d

### ENVファイルの初期設定

- cp .env.example .env
- ./vendor/bin/sail artisan key:generate

### データベースマイグレーション

- sail artisan migrate

### テストの実行

- ./vendor/bin/sail artisan test

### 開発環境の終了

- ./vendor/bin/sail down

## LINE Developer コンソールでの設定

- 新規チャンネルの作成（タイプはMessaging API）
- Messaging API設定のWebhookの利用をオンにしてWebhook URLを指定
- 応答メッセージを無効にする
- チャネルアクセストークンを発行する

## デプロイ

- .env のAPP_ENV、APP_DEBUG等を書き換える
- .env のチャネルアクセストークン設定する
- php artisan config:cache の実行
- php artisan route:cache の実行
- php artisan migrate

### データベースキューを使用する場合
- QUEUE_CONNECTION=sync --> database
- php artisan queue:work
