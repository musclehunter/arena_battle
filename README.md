# 1v1 Arena

ターン制 1vs1 バトルの Web アプリ。

- **v1.0**: 戦闘コアロジック(弱攻撃 / 強攻撃 / カウンター) → `arena_v1_architecture_ja.md`
- **v1.1**: 家門 (House) / 雇用 (Hiring) / ゲストプレイモデル → `arena_v1_1_scope_ja.md`
- **v1.2 / v1.2.1**: キャラクターステータス拡張(STR/VIT/DEX/INT) + ランク抽選による成長プリセット切替 → `arena_v1_2_scope_ja.md`
- **v1.3**: キャラクター画像表示 + `CharacterIcon` コンポーネント + Cloudflare R2 アセット配信

## 技術スタック

- バックエンド: Laravel 12 (PHP 8.3)
- フロント: Inertia.js + Vue 3 + Tailwind CSS + Ziggy
- DB: MySQL 8.0
- キャッシュ/セッション: Redis 7
- 実行環境: Docker Compose

## ディレクトリ構成

```
.
├─ backend/             # Laravel 本体
├─ docker/
│  ├─ php/Dockerfile    # PHP-FPM + Composer + Node 20 + Redis 拡張
│  └─ nginx/default.conf
├─ docker-compose.yml
├─ arena_v1_architecture_ja.md   # v1.0 アーキテクチャ(戦闘コア)
├─ arena_v1_1_scope_ja.md        # v1.1 スコープ(家門・雇用・ゲスト)
├─ arena_v1_2_scope_ja.md        # v1.2 スコープ(基本ステ + 成長プリセット)
├─ todo.md
└─ README.md
```

## 初回セットアップ

Docker Desktop が起動している前提。

### 1. コンテナをビルド

```powershell
docker compose build
```

### 2. Laravel プロジェクトを生成 (初回のみ)

`backend/` が空のとき、 `app` コンテナ内で Laravel を作成する。

```powershell
docker compose run --rm app composer create-project laravel/laravel .
```

### 3. `.env` を調整

`backend/.env` を開き、DB/Redis 接続を compose の値に合わせる。

```
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=arena
DB_USERNAME=arena
DB_PASSWORD=arena

CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

### 4. 依存関係インストール & アプリキー生成

```powershell
docker compose run --rm app php artisan key:generate
docker compose run --rm vite npm install
```

### 5. 起動

```powershell
docker compose up -d
```

- アプリ: http://localhost
- Vite HMR: http://localhost:5173
- MySQL: localhost:3306 (user: arena / pass: arena / db: arena)
- Redis: localhost:6379

### 6. マイグレーション & シード

```powershell
docker compose exec app php artisan migrate:fresh --seed
```

以下の順でシードされる(順序重要):

1. **`SystemAccountSeeder`** — システム用ダミーユーザー(`id=1`)。`config('arena.system_user_id')` と一致
2. **`GuestHouseSeeder`** — ゲスト雇用の受け皿となるダミー家門(`id=1`)。`config('arena.guest_house_id')` と一致
3. **`CharacterPresetSeeder`** — プレイヤー素体 4 種 (`hero_warrior/mage/rogue/priest`) + 敵 3 種 (`enemy_goblin/ogre/slime`)。各プリセットに `icon_key` を設定(戦士: `human_warrior_male`、その他: `null`)
4. **`JobSeekerSeeder`** — 求職者 10 名を `house_id=NULL` で作成。`icon_index` を決定論的に割り当て(`$i % 9`)

> seeder が `system_user_id` / `guest_house_id` の config と不一致になった場合は即時 `RuntimeException` が飛ぶので、`migrate:fresh` で綺麗な状態からやり直すこと。

## 遊び方

### ゲストプレイ (未ログイン)

1. http://localhost にアクセス(ゲスト初期所持金: **1000 G**)
2. 「求職者を見る」→ 3 名の求職者カードが表示される
3. 「ゲスト雇用して挑む」(`hire_cost × 1.5`、切り上げ) で即バトル開始
4. 勝利で **200 G** 獲得(キャラと `reward_share_bp` で分配。ゲストの場合、家門取り分はゲスト所持金に加算)
5. 勝利時は **EXP** も獲得(`enemy_level × 10`)。Lv が上がると成長プリセットに沿って基本ステが上昇
6. **バトル終了時にキャラは求職者プールへ自動復帰**(ゲスト雇用は一回限り)
7. バトル完了ごとに求職者ボードは再抽選される

### 家門プレイヤー (ログイン)

1. 新規登録 → `/houses/create` で家門名を入力(ゲストの 1000 G は破棄され、家門 1000 G で再スタート)
2. `/houses/mine` で家門ダッシュボード
   - Lv.1 の雇用枠は 3 人
   - 雇用中のキャラに「この子で挑む」「解雇」が可能(解雇時キャラの所持金は保持)
3. `/job-seekers` で「家門で雇用」(家門 gold から `hire_cost` 支払い)も可能
4. 家門プレイヤーも「ゲスト雇用して挑む」(家門 gold から 1.5 倍) を利用できる
5. 勝利報酬は家門 gold に加算

### 制限事項

- 1 ユーザーにつき家門は 1 つ(`users.id` UNIQUE)
- **同時進行バトルは家門/ゲストセッションごとに 1 件**(違反は `ConcurrentBattleException`)
- ゲストバトルの再戦は不可(求職者から再度雇用し直す)

## キャラクターステータス & レベリング (v1.2)

### 基本ステ 4 種 → 派生ステ

| 基本ステ | 表記 | 主な役割 |
|---|---|---|
| `str` | 力 | ATK 主・HP 補助 |
| `vit` | 体 | HP 主・DEF 補助 |
| `dex` | 器 | ATK / DEF 補助 |
| `int_stat` | 魔 | 未使用(将来のスキル威力用) |

```
HP_max = floor(STR*1.0 + VIT*2.0) + 15
ATK    = floor(STR*1.0 + DEX*0.5)
DEF    = floor(VIT*0.5 + DEX*0.5)
```

係数は `config/arena.php` の `derived_stats` で変更可能。計算は `App\Services\Character\CharacterStats` に集約(純関数)。

### 成長プリセット & ランク抽選サイクル (v1.2.1)

各プリセットは **10 要素の増分配列** を持ち、Lvup 時に `growth_index` の位置の増分を基本ステに加算する。 index=9 の **壁ステップは通常の約 3 倍** の上昇量で、必要 EXP も相応に重い。

プリセットは `{job}_{rank}` 形式 (例: `warrior_normal`)。4 職 × 5 ランク + 敵 5 ランク = 25 種類。ランクは `easy → normal → hard → expert → master` の 5 段階で、倍率は `0.7 / 1.0 / 1.4 / 1.8 / 2.3`。

10 Lv 消化時 (`growth_index == 10`) に **ランク抽選箱** から 1 つ引いて次のプリセットを決定する(同一職内で遷移):

- 初期箱 = `現在ランク × 2 + 次ランク × 1` (config `arena.rank_box.*`)
- **維持** (現在ランクを引いた): 箱から引いた分 1 つを除外するだけ
- **ランクアップ** (次ランクを引いた): 箱内の全ランクを **1 段シフトアップ** し、旧 current × 1 と新 next × 1 を追加
- **ランクダウン** (下位ランクを引いた): 箱内の全ランクを **1 段シフトダウン** するのみ(追加なし)
- シフトは循環(master→easy / easy→master)

敵も `enemy_easy 〜 enemy_master` の 5 ランクが用意されており同じロジックで扱えるが、通常は Lvup しない想定。

### EXP / Lvup 仕様

- 勝利時 EXP: `enemy_level × config('arena.leveling.exp_per_enemy_level')` (デフォルト 10)
- 敗北・引き分けは EXP 獲得なし
- 次 Lv 必要 EXP = `(現 growth_index の 4 ステ増分合計) × required_exp_per_stat_point` (デフォルト 10)
  - 通常 step (合計 ≈ 4〜5) → 40〜50 EXP
  - 壁 step (合計 ≈ 12〜17) → 120〜170 EXP
- 1 バトルで Lvup できる上限は `max_level_ups_per_battle` (= 5)
- `App\Services\Character\LevelUpService::grantExp()` が EXP 加算・Lvup ループ・プリセット切替を一括処理

## 主要ルート

| メソッド | パス | 認証 | 機能 |
|---|---|---|---|
| GET | `/` | 任意 | ゲストランディング(家門ありなら `/houses/mine` へリダイレクト) |
| GET | `/job-seekers` | 任意 | 求職者 3 名 |
| POST | `/guest-hires` | 任意 | ゲスト雇用 + バトル自動開始 |
| GET | `/houses/create` | 必須 | 家門作成フォーム |
| POST | `/houses` | 必須 | 家門作成 |
| GET | `/houses/mine` | 必須 | 家門ダッシュボード |
| POST | `/houses/hire` | 必須 | 家門雇用 |
| POST | `/houses/release/{character}` | 必須 | 解雇 |
| POST | `/battles` | 必須 | 家門バトル開始 |
| GET | `/battles/{battle}` | 任意 | バトル画面(所有者のみ) |
| POST | `/battles/{battle}/turn` | 任意 | ターン解決 |
| POST | `/battles/{battle}/restart` | 任意 | 再戦(家門バトルのみ) |

## テスト

```powershell
docker compose exec app php artisan test
```

主要テスト(v1.2.1 時点で 87 pass / 283 assertions):

- **戦闘コア (v1.0 継承)**
  - `Tests\Unit\Services\Battle\DamageCalculatorTest` — 9 通りの行動組み合わせ + 左右対称性
  - `Tests\Unit\Services\Battle\BattleLogFactoryTest` — ログ文言の固定化
  - `Tests\Feature\BattleTurnResolutionTest` — バトル開始 / ターン解決 / 決着 / token 検証
  - `Tests\Feature\BattleRestartTest` — 再戦フロー(ゲストバトルは不可)

- **v1.1 新規**
  - `Tests\Feature\HouseCreationTest` — 家門作成 / ゲスト資産破棄 / 二重作成禁止 / バリデーション
  - `Tests\Feature\HouseHiringTest` — 家門雇用 / ゴールド不足 / 枠超過 / 解雇 / 他家門キャラ拒否
  - `Tests\Feature\GuestHireFlowTest` — ゲスト雇用+自動バトル開始 / 1.5 倍コスト / 二重雇用拒否 / 終了時自動解雇
  - `Tests\Feature\RewardDistributionTest` — 家門勝利時の分配 / ゲスト勝利時のセッション加算 / 敗北時 0
  - `Tests\Feature\BattlePolicyTest` — 所有者以外の閲覧/操作拒否
  - `Tests\Feature\ConcurrentBattleTest` — 同一家門 / 同一ゲストセッションの同時進行 1 件制限

- **v1.2 / v1.2.1 新規**
  - `Tests\Unit\Services\Character\CharacterStatsTest` — 基本ステ→派生ステの計算 / 境界値
  - `Tests\Feature\LevelUpServiceTest` — 必要 EXP 計算 / EXP 付与 Lvup / 初期抽選箱 / ランク維持・アップ・ダウン / easy・master 境界 / 10 Lv 消化抽選 / 獲得 EXP

テストフィクスチャは `tests/Concerns/CreatesArenaFixtures.php` に集約。

## キャラクター画像 (v1.3)

### アイコン仕様

- **画像パス**: `{VITE_ASSET_BASE_URL}/characters/icons/400/{icon_key}_{icon_index}_400.png`
- **`icon_key`**: `character_presets.icon_key` に保存。現在は戦士のみ `human_warrior_male`、他は `null`(枠のみ表示)
- **`icon_index`**: `characters.icon_index` に保存。0〜8 の範囲でキャラ生成時に決定論的に割り当て
- **画像なし**: `icon_key` が `null` のキャラはグレーのプレースホルダー枠を表示
- 枠・背景のスタイルは `CharacterIcon.vue` に集約しており、今後レア度・強さ対応で変更可能

### アセット配信 (Cloudflare R2)

- 画像バイナリは `public/images/` に置かず、Cloudflare R2 に配信を任せる
- `public/images/` は `.gitignore` 対象(git 管理外)
- `VITE_ASSET_BASE_URL` が空の場合は同一オリジンの `/characters/...` を参照(ローカル開発時は R2 URL を設定)

```
VITE_ASSET_BASE_URL=https://pub-acec75c0c97b45aa80aaf02feacb4b8b.r2.dev
```

### 関連ファイル

- `resources/js/Components/CharacterIcon.vue` — アイコン表示コンポーネント(props: `icon-key` / `icon-index` / `alt` / `size`)
- `database/migrations/2026_04_24_000002_add_icon_index_to_characters_table.php` — `characters.icon_index` 追加
- `character_presets.icon_key` は既存カラム

## アーキテクチャの概要

### 戦闘コア (v1.0)

- **`App\Services\Battle\DamageCalculator`** — 行動 + 両者の ATK/DEF から `TurnResolution` を算出。ダメージ = `max(min, floor(ATK * multiplier) - DEF)`。係数は `config('arena.damage')`
- **`App\Services\Battle\EnemyActionDecider`** — 前ターンのプレイヤー行動を見る簡易ヒューリスティック。`battle_id + turn_number` で決定論的シード
- **`App\Services\Battle\BattleLogFactory`** — `summary_text` 生成専任
- **`App\Actions\Battle\StartBattleAction`** — `Character` + `BattleContext` を受けてバトル作成(同時進行チェック)
- **`App\Actions\Battle\ResolveTurnAction`** — `lockForUpdate` で直列化。決着時に `RewardDistributor` / `HiringService::autoReleaseAfterGuestBattle` / `JobSeekerBoard::invalidate` を連携
- **`App\Actions\Battle\RestartBattleAction`** — 既存バトルの `player_character` と `enemy_preset` を引き継いで再戦

### v1.1 ドメイン層

- **`App\Services\Arena\GuestContext`** — セッションに持つゲスト状態 (gold / hired_character_id / job_seeker_ids)
- **`App\Services\Arena\JobSeekerBoard`** — 表示中 3 名の抽選・維持・破棄(バトル完了時に再抽選)
- **`App\Services\Arena\HiringService`** — 家門雇用 / ゲスト雇用(1.5 倍) / 解雇 / ゲストバトル後の自動解雇
- **`App\Services\Arena\RewardDistributor`** — 勝利時 200 G を `reward_share_bp` で分配(家門 / ゲストセッションへ)
- **`App\Services\Battle\BattleContext`** — 家門バトル or ゲストバトルを表す不変 VO
- **`App\Actions\House\CreateHouseAction`** — 家門作成 + ゲスト資産破棄
- **`App\Actions\Battle\GuestHireAndStartBattleAction`** — ゲスト雇用 + 即バトル開始の複合アクション

### 認可 (Policy)

- **`App\Policies\BattlePolicy`** — `user_id` 一致 (家門) / `guest_session_id` 一致 (ゲスト)
- **`App\Policies\CharacterPolicy`** — `release` / `hireByHouse` を自家門限定

### 二重送信対策 (action_token)

`battles.action_token` にランダム文字列を保存し、ターン解決後に毎回更新する使い捨てチケット。クライアントは Inertia props 経由で受け取り、次の行動送信に添付。サーバは `hash_equals()` で定数時間比較。連打・ブラウザバック再送・古いタブからの送信を弾く。

### v1.2 ドメイン層

- **`App\Services\Character\CharacterStats`** — 基本ステ → 派生ステ純関数。`derive` / `forEntity` / `forPreset`
- **`App\Services\Character\LevelUpService`** — EXP 加算 / Lvup ループ / ランク抽選によるプリセット切替 (`advanceRank`) / 必要 EXP 算出
- **`App\Services\Character\GrowthRank`** — ランク定義 / 上下・次の算出 / 初期抽選箱生成 / key 組立
- **`App\Models\GrowthPreset`** — `incrementAt(int $index)` / `job` / `rank` / `rank_order`

### 設定値 (`config/arena.php`)

主要定数を一箇所に集約。代表値:

- `system_user_id` / `guest_house_id` = 1 (seeder と同期)
- `house_level_slots` = [1 => 3]
- `initial_gold` = { guest: 1000, house: 1000, character: 50 }
- `reward.win_total` = 200
- `guest_hire_multiplier` = 1.5
- `job_seeker` = { pool_size: 10, visible_count: 3, ... }
- `derived_stats` = { hp_const: 15, str_hp: 1.0, vit_hp: 2.0, str_atk: 1.0, dex_atk: 0.5, vit_def: 0.5, dex_def: 0.5 }
- `damage` = { weak_multiplier: 1.0, strong_multiplier: 2.0, counter_multiplier: 1.5, min_damage: 1 }
- `leveling` = { exp_per_enemy_level: 10, required_exp_per_stat_point: 10, max_level: 99, max_level_ups_per_battle: 5 }
- `rank_box` = { initial_current: 2, initial_next: 1, add_lower_on_rankup: 1, add_next_on_rankup: 1 }

## Railway デプロイ

本番は Railway で運用する想定。リポジトリ: `git@github.com:musclehunter/arena_battle.git`

### 前提ファイル (同梱済み)

- `backend/Dockerfile` — multi-stage production ビルド(Node→PHP)
- `backend/.dockerignore` — vendor / node_modules / tests 等を除外
- `backend/.env.example` — MySQL / Redis を Railway 既定で参照
- `backend/bootstrap/app.php` — `trustProxies('*')` で HTTPS / ホスト名を認識

### Railway 操作手順

1. **プロジェクト作成**: Railway.com → "New Project" → "Deploy from GitHub repo" → `musclehunter/arena_battle`
2. **Service Settings**:
    - Root Directory = `backend`
    - Builder = Dockerfile(自動検出)
    - Healthcheck Path = `/up`(Laravel デフォルト)
3. **プラグイン追加** (同じプロジェクト内):
    - "New" → "Database" → **MySQL**
    - "New" → "Database" → **Redis**
4. **Web service の Variables を設定** (Railway の変数参照 `${{サービス名.変数名}}` を活用):

    ```env
    APP_NAME=ArenaBattle
    APP_ENV=production
    APP_KEY=                                # 下記コマンドで生成した値を貼る
    APP_DEBUG=false
    APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}
    APP_TIMEZONE=Asia/Tokyo
    APP_LOCALE=ja

    LOG_CHANNEL=stderr
    LOG_LEVEL=info

    DB_CONNECTION=mysql
    DB_HOST=${{MySQL.MYSQLHOST}}
    DB_PORT=${{MySQL.MYSQLPORT}}
    DB_DATABASE=${{MySQL.MYSQLDATABASE}}
    DB_USERNAME=${{MySQL.MYSQLUSER}}
    DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

    REDIS_CLIENT=phpredis
    REDIS_HOST=${{Redis.REDISHOST}}
    REDIS_PORT=${{Redis.REDISPORT}}
    REDIS_PASSWORD=${{Redis.REDISPASSWORD}}

    SESSION_DRIVER=redis
    CACHE_STORE=redis
    QUEUE_CONNECTION=redis

    SESSION_SECURE_COOKIE=true
    SESSION_SAME_SITE=lax

    # Cloudflare R2 アセット配信 (画像・アイコン)
    VITE_ASSET_BASE_URL=https://pub-acec75c0c97b45aa80aaf02feacb4b8b.r2.dev
    ```

5. **`APP_KEY` 生成** (ローカルで):

    ```powershell
    docker compose exec app php artisan key:generate --show
    ```

    出力された `base64:...` を Railway の `APP_KEY` に設定。

6. **初回デプロイ**: push すると自動ビルド。Dockerfile の `CMD` が起動時に `config:cache` → `route:cache` → `view:cache` → `migrate --force` を自動実行する。

7. **初回だけ seed** (Railway の Web service シェルから):

    ```bash
    php artisan db:seed --force
    ```

8. **公開 URL を有効化**: service settings → Networking → "Generate Domain"

### スケール時の検討

- `php artisan serve` は単一プロセス。トラフィック増加時は FrankenPHP / Octane + RoadRunner への切り替えを検討
- セッションは Redis 集約済みなので水平スケール(レプリカ増)はそのまま可能

## 停止

```powershell
docker compose down
```

データボリュームも消したい場合:

```powershell
docker compose down -v
```

## よく使うコマンド

```powershell
# Laravel Artisan
docker compose exec app php artisan <command>

# DB リセット + シード(開発中によく使う)
docker compose exec app php artisan migrate:fresh --seed

# テスト
docker compose exec app php artisan test

# Composer
docker compose exec app composer <command>

# npm (vite コンテナ経由)
docker compose exec vite npm <command>

# MySQL CLI
docker compose exec db mysql -u arena -parena arena

# Redis CLI
docker compose exec redis redis-cli
```
