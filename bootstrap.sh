#!/usr/bin/env bash
set -eu

# Usage: run this inside a clean working folder that is already a local clone of
#   git@github.com:sakaisan1125/coachtech-attendance-app.git
# Example:
#   git clone git@github.com:sakaisan1125/coachtech-attendance-app.git
#   cd coachtech-attendance-app
#   bash bootstrap.sh

PROJECT_ROOT=$(pwd)

say() { printf "\n[setup] %s\n" "$*"; }

say "Create Docker files"
mkdir -p docker/php docker/nginx

cat > docker-compose.yml <<'YAML'
version: "3.9"
services:
  php:
    build: ./docker/php
    volumes:
      - ./:/var/www/html
    working_dir: /var/www/html
    depends_on:
      - mysql
  nginx:
    image: nginx:1.27-alpine
    ports:
      - "80:80"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: laravel_db
      MYSQL_USER: laravel_user
      MYSQL_PASSWORD: laravel_pass
      MYSQL_ROOT_PASSWORD: root
    ports:
      - "3306:3306"
    command: ["--default-authentication-plugin=mysql_native_password"]
    volumes:
      - mysql_data:/var/lib/mysql
  mailhog:
    image: mailhog/mailhog:latest
    ports:
      - "8025:8025"
volumes:
  mysql_data:
YAML

cat > docker/php/Dockerfile <<'DOCKER'
FROM php:8.3-fpm
RUN apt-get update \
 && apt-get install -y git unzip libicu-dev libonig-dev libzip-dev libpng-dev \
 && docker-php-ext-install pdo_mysql intl zip
# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
DOCKER

cat > docker/nginx/default.conf <<'NGINX'
server {
  listen 80;
  server_name localhost;
  root /var/www/html/public;
  index index.php index.html;
  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }
  location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass php:9000;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  }
}
NGINX

say "docker-compose up -d --build"
docker-compose up -d --build

say "Install fresh Laravel 11 inside container"
docker-compose exec php bash -lc 'composer create-project laravel/laravel . && php artisan key:generate'

say "Setup .env"
cat > .env <<'ENV'
APP_NAME="Attendance"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
APP_TIMEZONE=Asia/Tokyo

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel_db
DB_USERNAME=laravel_user
DB_PASSWORD=laravel_pass

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=noreply@attendance.local
MAIL_FROM_NAME="Attendance"
ENV

# Re-add the generated key
docker-compose exec php bash -lc 'php artisan key:generate'

say "Migrate base tables"
docker-compose exec php bash -lc 'php artisan migrate'

say "Install Fortify (auth)"
docker-compose exec php bash -lc 'composer require laravel/fortify && php artisan vendor:publish --provider="Laravel\\Fortify\\FortifyServiceProvider" && php artisan migrate'

say "Add role column migration"
mkdir -p database/migrations
ROLE_MIG="database/migrations/$(date +%Y_%m_%d_%H%M%S)_add_role_to_users_table.php"
cat > "$ROLE_MIG" <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('employee'); // employee/approver/admin
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
PHP

docker-compose exec php bash -lc 'php artisan migrate'

say "Seed 10 users (admin/approver/8 employees)"
cat > database/seeders/UserSeeder.php <<'PHP'
<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        User::factory()->create([
            'name' => 'Approver',
            'email' => 'approver@example.com',
            'password' => Hash::make('password'),
            'role' => 'approver',
        ]);

        for ($i = 1; $i <= 8; $i++) {
            User::factory()->create([
                'name' => "Employee{$i}",
                'email' => "employee{$i}@example.com",
                'password' => Hash::make('password'),
                'role' => 'employee',
            ]);
        }
    }
}
PHP

# Enable seeder in DatabaseSeeder
perl -0777 -pe 's/public function run\(\): void\s*\{[\s\S]*?\}/public function run(): void\n    {\n        \$this->call(\n            [UserSeeder::class]\n        );\n    }/s' -i database/seeders/DatabaseSeeder.php

say "Run seeder"
docker-compose exec php bash -lc 'php artisan db:seed'

say "README draft"
cat > README.md <<'MD'
# coachtech 勤怠管理アプリ（MVPブートストラップ）

## 起動手順（初回）
```bash
git clone git@github.com:sakaisan1125/coachtech-attendance-app.git
cd coachtech-attendance-app
docker-compose up -d --build
docker-compose exec php bash -lc "composer create-project laravel/laravel . && php artisan key:generate"
cp .env .env.local  # 必要なら
# 以降は本READMEの .env 設定を参照
php artisan migrate
```

> MailHog: http://localhost:8025

### .env（主要）
- DB_* は docker-compose.yml に合わせてあります
- タイムゾーン: Asia/Tokyo

### 初期ユーザー（Seeder）
- Admin: `admin@example.com` / `password`
- Approver: `approver@example.com` / `password`
- Employee1〜8: `employee{n}@example.com` / `password`

### バージョン
- Laravel 11 / PHP 8.3 / MySQL 8.0

## 今後の実装（このリポのIssue/PRで管理）
- 勤怠打刻 API（IN/OUT/BREAK IN/OUT）
- 日次/月次、編集申請→承認、CSV（管理者のみ）
- 休暇（年休/欠勤）
- 権限（employee/approver/admin）
MD

say "Git add & commit"
echo ".env" >> .gitignore

# If laravel already created vendor etc., just commit everything

git add .
git commit -m "chore: bootstrap docker + laravel + fortify + seeds"

say "Done. Now push with: git push -u origin main"
