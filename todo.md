# 1v1アリーナ v1 実装Todo

`arena_v1_architecture_ja.md` をもとに、実装作業を粒度を揃えた粗い順で並べたチェックリストです。
各ステップが完了したら `[ ]` を `[x]` に置き換えて進捗を管理します。

---

## ステップ0: 環境構築

- [x] Docker Compose 一式を用意する (app / web / vite / db / redis)
- [x] Laravel プロジェクトを `backend/` に新規作成する
- [x] Inertia.js + Vue 3 を導入する (Breeze Vue スタック)
- [x] Tailwind CSS を導入し、ビルド確認する (`npm run build` 成功)
- [x] MySQL 接続設定を `.env` に記述し、接続確認する (migrate 成功)
- [x] Redis を導入し、キャッシュ・セッションドライバとして設定する
- [x] `docker compose up -d` で `http://localhost` に 200 応答することを確認

---

## ステップ1: DBとモデル

### マイグレーション

- [ ] `users` に必要なら `name` / `current_rating` を追加する (v1 では保留)
- [x] `character_presets` マイグレーション作成
- [x] `battles` マイグレーション作成
- [x] `battle_logs` マイグレーション作成

### モデル

- [x] `CharacterPreset` モデル作成
- [x] `Battle` モデル作成 + リレーション(`user` / `playerPreset` / `enemyPreset` / `logs`)
- [x] `BattleLog` モデル作成 + `battle` リレーション

### シーダー

- [x] `CharacterPresetSeeder` 作成 (`player_basic` / `enemy_basic`)
- [x] `DatabaseSeeder` に登録し、`php artisan migrate:fresh --seed` が通ることを確認

---

## ステップ2: Enum とドメイン型

- [x] `App\Enums\BattleActionType` を作成 (`weak` / `strong` / `counter`)
- [x] `App\Enums\BattleStatus` を作成 (`in_progress` / `finished`)
- [x] `App\Enums\BattleWinner` を作成 (`player` / `enemy` / `draw`)
- [x] `Battle` / `BattleLog` モデルで Enum を cast する

---

## ステップ3: ダメージ計算(ロジックコア)

- [x] `App\Services\Battle\DamageCalculator` を作成(結果 DTO: `TurnResolution` / `TurnOutcome`)
- [x] 9.4 の全組み合わせを網羅する PHPUnit テストを書く
  - [x] 弱 vs 弱
  - [x] 弱 vs 強
  - [x] 弱 vs カウンター
  - [x] 強 vs 弱
  - [x] 強 vs 強
  - [x] 強 vs カウンター
  - [x] カウンター vs 弱
  - [x] カウンター vs 強
  - [x] カウンター vs カウンター
- [x] 左右対称性テストを追加
- [x] テスト緑化(10 passed / 72 assertions)

---

## ステップ4: 静的画面(見た目の器)

- [x] `resources/js/Pages/Battle/Show.vue` 作成(コンポーネント集約)
- [x] `Components/Battle/BattleStatusPanel.vue` 作成(HPバー + 色切替)
- [x] `Components/Battle/BattleLogPanel.vue` 作成(新ログで自動スクロール)
- [x] `Components/Battle/BattleActionButtons.vue` 作成(弱/強/カウンター)
- [x] `Components/Battle/BattleResultPanel.vue` 作成(勝敗テキスト色分け + 再戦ボタン)
- [x] Show.vue をコンポーネントで再構成し `npm run build` 成功

---

## ステップ5: バトル開始

- [x] `App\Actions\Battle\StartBattleAction` 作成
  - プレイヤー/敵 preset 決定 → `battles` 作成 → 初期ログ作成
- [x] `BattleController@index` (トップ/開始ボタン)
- [x] `BattleController@store` → `StartBattleAction` 呼び出し
- [x] `BattleController@show` → Inertia で `Battle/Show` に現在状態を渡す
- [x] ルーティング登録 (`/`, `POST /battles`, `GET /battles/{battle}`)
- [x] `/battles/{id}` が実データで表示されることを確認(Inertia props で HP/ログ到達)
- [x] 最小の `Pages/Battle/Index.vue` / `Pages/Battle/Show.vue` を用意(コンポーネント分割はステップ4で実施)

---

## ステップ6: 1ターン解決

- [x] `App\Services\Battle\EnemyActionDecider` 作成(プレイヤー前行動を見る簡易ヒューリスティック)
- [x] `App\Services\Battle\BattleLogFactory` 作成(宣言 → 結果 → 決着 → 現在 HP の順)
- [x] `App\Actions\Battle\ResolveTurnAction` 作成(行ロック・ログ保存・HP/ターン/status 更新・token 再発行)
- [x] `App\Http\Requests\Battle\SubmitBattleActionRequest` 作成(`action` / `token` バリデーション)
- [x] `status === in_progress` の検証(コントローラ内)
- [x] token 検証(`hash_equals` で定数時間比較)
- [x] `battles` テーブルに `action_token` カラム追加
- [x] `BattleController@resolveTurn` 実装
- [x] ルーティング登録 (`POST /battles/{battle}/turn`)
- [x] 画面アクションボタン接続 + 結末表示 + 再戦ボタン
- [x] Feature テスト 5 件緑化(正常/敗北/不正 token/終了済み)

---

## ステップ7: 勝敗判定

- [x] HP 0 以下で `status = finished` に更新
- [x] `winner` を `player` / `enemy` / `draw` で確定
- [x] `ended_at` を保存
- [x] 決着後は行動ボタンを非表示 → 結末パネル + 再戦ボタン表示
- [x] `BattleResultPanel` コンポーネントに分離(ステップ4で実施済み)

---

## ステップ8: 再戦

- [x] `App\Actions\Battle\RestartBattleAction` 作成(既存 battle の preset を元に新規 battle 生成)
- [x] `StartBattleAction` を preset ID 指定可能に拡張(再戦と新規で共通化)
- [x] `BattleController@restart` 実装
- [x] ルーティング登録 (`POST /battles/{battle}/restart`)
- [x] Show.vue の再戦ボタンを `battles.restart` に切替
- [x] Feature テスト 3 件緑化(同 preset で新規生成 / 元 battle 不変 / リダイレクト)

---

## ステップ9: 仕上げ・検証

- [x] 10章のログ文言の順序を調整(非ダメージ系 → ダメージ系)
- [x] `BattleLogFactoryTest` で設計書10章の 3 例を固定化
- [x] 決着後の送信は `status` エラー、不正トークンは `token` エラーで弾かれることを `BattleTurnResolutionTest` でカバー
- [x] `DamageCalculator` テスト 10 件全パス
- [x] README に遊び方・テスト・アーキテクチャ概要・action_token 仕様を記載
- [x] 全 46 テスト / 173 アサーション 緑

---

## v1 スコープ外(やらない)

- アニメーション / 音 / 装備 / 複数敵 / 属性 / クリティカル / 回避 / 通信対戦 / ガチャ / ランキング / マップ移動

---

# v1.1: 家門 & 雇用モデル

スコープは `arena_v1_1_scope_ja.md` に確定済み。

## ステップ10: 基盤整備

- [x] `config/arena.php` を作成(雇用上限・初期gold・報酬・割增率・求職者パラメータ・`guest_house_id=1` / `system_user_id=1`)
- [~] 既存テストは既に壊れている(battles スキーマ変更のため)。ステップ14-17 で修復予定

## ステップ11: マイグレーション & モデル

- [x] `houses` テーブル作成
- [x] `characters` テーブル作成 (`house_id nullable FK → houses`)
- [x] `battles` テーブル変更(既存 migration を直接編集)
  - `house_id` / `guest_session_id` / `player_character_id` / `reward_gold_*` 追加
  - `player_preset_id` 削除
- [x] migration 順序調整(houses 181308 / characters 181310 を挑む)
- [x] `migrate:fresh` が通ることを確認
- [x] `House` / `Character` モデル(リレーション・状態判定メソッド・スコープ)
- [x] `User ↔ House` の `hasOne`
- [x] `Battle` モデルを v1.1 スキーマに更新(`playerCharacter` / `house` / `isGuestBattle`)

## ステップ12: ドメインサービス

- [x] `GuestContext` (セッションの gold / hired_character_id / job_seeker_ids を扱う)
- [x] `JobSeekerBoard` (3名取得 / バトル完了時に破棄 / 候補絞り込みクエリ)
- [x] `HiringService`
  - [x] `hireByHouse(House, Character)`
  - [x] `hireAsGuest(GuestContext, Character)` (`hire_cost × 1.5`)
  - [x] `release(House, Character)` (gold 保持)
  - [x] `autoReleaseAfterGuestBattle(Battle)`
- [x] `RewardDistributor` (勝利時のみ、bp で分配、ゲスト/家門で振り分け先を切替)

## ステップ13: Seeder

- [x] `SystemAccountSeeder` 作成(ダミー user `system@arena.local` / id sanity check)
- [x] `GuestHouseSeeder` 作成(ダミー user に紐づく "Guest House" / id sanity check)
- [x] `CharacterPresetSeeder` を整備(プレイヤー用プリセットを複数種類)
- [x] `JobSeekerSeeder` 作成(10名 / 固有名リスト / Lv1 / gold=50 / hire_cost と share_bp を算出)
- [x] `DatabaseSeeder` に順序固定で登録 (System → GuestHouse → Preset → JobSeeker)

## ステップ14: Actions 改修

- [x] `CreateHouseAction` 追加(家門名入力 / 既存ゲスト gold は破棄 / house gold=1000 で作成)
- [x] `HireCharacterAction` 追加(家門雇用 / 枠チェック / gold チェック)
- [x] `GuestHireCharacterAction` 追加(ゲスト雇用 → バトル即開始の呼び出し元)
- [x] `ReleaseCharacterAction` 追加(解雇 / キャラ gold は保持)
- [x] `StartBattleAction` 改修
  - `Character` を受け取り、preset ID ではなく `player_character_id` を保存
  - 同時進行バトル1件の制約チェック(家門/ゲスト両対応)
- [x] `ResolveTurnAction` 改修
  - 決着時に `RewardDistributor` を呼ぶ
  - ゲスト雇用バトルは `autoReleaseAfterGuestBattle` を呼ぶ
  - 決着時に `JobSeekerBoard::invalidate()` を呼ぶ
- [x] `RestartBattleAction` 改修
  - 家門雇用中キャラなら同キャラで再戦 (house_id据え置き)
  - ゲスト雇用は再戦不可 or 都度再雇用(→ ボタン出し分け)

## ステップ15: Controller & Routing

- [x] `HouseController` (create form / store / show = ダッシュボード)
- [x] `JobSeekerController@index` (ゲスト・家門両対応)
- [x] `CharacterController@release`
- [x] `GuestHireController@store` (ゲスト雇用してバトル開始まで)
- [x] `HireController@store` (家門雇用のみ)
- [x] `BattleController` 改修
  - 所有者認可(user or guest_session_id)
  - レスポンスに報酬情報を含める
- [x] `routes/web.php` にルート追加
- [x] Policies: `BattlePolicy` / `HousePolicy` / `CharacterPolicy`

## ステップ16: Vue / Inertia

- [x] `Pages/Home.vue` 改修(ゲスト導線 / ログイン済みは家門へリダイレクト)
- [x] `Pages/JobSeekers/Index.vue` 新規(3カード / 雇用ボタン2種)
- [x] `Pages/House/Create.vue` 新規(家門名入力)
- [x] `Pages/House/Mine.vue` 新規(雇用枠表示 / キャラ一覧 / 解雇ボタン)
- [x] `Pages/Battle/Show.vue` 改修(キャラ名・家門情報・報酬表示)
- [x] `Components/JobSeekerCard.vue`
- [x] `Components/CharacterCard.vue`(家門ダッシュボード用)
- [x] `Components/GoldBadge.vue`(gold表示の共通部品)

## ステップ17: テスト

- [x] `HiringService` のユニットテスト
- [x] `RewardDistributor` のユニットテスト(分配計算 / ゲスト分岐)
- [x] Feature: ゲスト雇用 → バトル → 完了で求職者に戻る
- [x] Feature: 家門作成 → 継続雇用 → 勝利で報酬分配
- [x] Feature: 家門雇用枠 3 超過 → 422
- [x] Feature: gold 不足で雇用 → 422
- [x] Feature: 同時進行バトル1件制限(家門・ゲスト)
- [x] Feature: 雇用中キャラは求職者リストに出てこない
- [x] Feature: バトル完了でリスト破棄 → 次回訪問で再抽選
- [x] Feature: ゲスト gold 破棄 → 家門作成で 1000 から始まる
- [x] Feature: 解雇するとキャラの gold が保持される

## ステップ18: 仕上げ

- [x] README に v1.1 遊び方・概念(家門/求職者/雇用)を追記
- [x] 設計書 `arena_v1_architecture_ja.md` に v1.1 章を追加 or スコープMD と相互リンク
- [x] 全テスト緑化確認

---

# v1.2: キャラクターステータス拡張 & レベリング

スコープは `arena_v1_2_scope_ja.md` に確定済み。

## ステップ19: 設計・設定

- [x] `config/arena.php` に `derived_stats` / `leveling` セクションを追加
- [x] `arena_v1_2_scope_ja.md` を作成(基本ステ / 派生ステ / 成長プリセット / EXP 仕様)

## ステップ20: マイグレーション & モデル

- [x] `growth_presets` テーブル追加(`key` UNIQUE / `increments` JSON 10要素 / `next_preset_key`)
- [x] `character_presets` を v1.2 仕様に変更(`hp_max/atk/def` → `base_str/vit/dex/int` + `base_level` + `growth_preset_key`)
- [x] `characters` に `exp` / `str/vit/dex/int_stat` / `growth_preset_key` / `growth_index` を追加
- [x] `GrowthPreset` モデル (`incrementAt` / `nextKey`) を新設
- [x] `Character` / `CharacterPreset` モデルに新カラムを反映
- [x] `migrate:fresh --seed` が通ることを確認

## ステップ21: ドメインサービス

- [x] `App\Services\Character\CharacterStats` (`derive` / `forEntity` / `forPreset`)
- [x] `App\Services\Character\LevelUpService`
  - [x] `rewardExpFromEnemyLevel(int)`
  - [x] `requiredExpToNext(Character)`
  - [x] `grantExp(Character, int)`(Lvup ループ / `growth_index` 進行 / プリセット切替)

## ステップ22: Seeder

- [x] `GrowthPresetSeeder` 追加(warrior → rogue → mage → priest → warrior のサイクル + `enemy_growth`)
- [x] `DatabaseSeeder` に `GrowthPresetSeeder` を `CharacterPresetSeeder` より前に登録
- [x] `CharacterPresetSeeder` を新スキーマで書き直し(プレイヤー素体 4 種 + 敵 3 種)
- [x] `JobSeekerSeeder` で Character に基本ステ / 成長プリセット / EXP=0 / growth_index=0 を付与

## ステップ23: Actions / Controller / ViewModel 改修

- [x] `StartBattleAction` を `CharacterStats::forEntity/forPreset` で HP 派生計算に変更
- [x] `ResolveTurnAction` 勝利時に `LevelUpService::grantExp` を呼び EXP 付与
- [x] `HouseController` / `JobSeekerController` で Lv / EXP / 基本ステ / 派生ステを props に含める
- [x] `BattleViewModel` で player/enemy の Lv / EXP / 基本ステ / 派生ステを返す

## ステップ24: Vue / Inertia

- [x] `Pages/JobSeekers/Index.vue` でカードに Lv / EXP / 力体器魔 / ATK / DEF を表示
- [x] `Pages/House/Mine.vue` のキャラ一覧に同情報を表示
- [x] `Components/Battle/BattleStatusPanel.vue` に `level` / `stats` props を追加
- [x] `Pages/Battle/Show.vue` で `BattleStatusPanel` に Lv / stats を渡す

## ステップ25: 認証画面の UI 統一

- [x] `GuestLayout` をゲームと同じダークトーンに再デザイン
- [x] `Auth/Login` / `Register` / `ForgotPassword` / `ResetPassword` / `ConfirmPassword` / `VerifyEmail` を統一スタイルに更新

## ステップ26: テスト

- [x] Unit: `CharacterStatsTest`(派生計算の基本 + 境界)
- [x] Feature: `LevelUpServiceTest`(必要 EXP / Lvup / プリセット切替 / 獲得 EXP 計算)
- [x] 既存 Battle / Hire / Reward 系テストを新 API に追従
- [x] 全テスト緑化確認 (78 passed / 265 assertions)

## ステップ27: ドキュメント

- [x] `arena_v1_2_scope_ja.md` を整備
- [x] `todo.md` に v1.2 セクション追加・v1.1 の済み項目を反映
- [x] README に v1.2 の遊び方(Lv / EXP / 成長プリセットサイクル / 壁ステ)を追記

---

# v1.2.1: 成長プリセットのランク体系化

固定サイクル(職替え)を廃し、各職に 5 段階ランク(easy〜master)を用意。
同職内でランク抽選により成長プリセットが切り替わる仕組みを導入。

## ステップ28: スキーマ / モデル

- [x] `growth_presets` に `job` / `rank` / `rank_order` を追加、`next_preset_key` を廃止
- [x] `characters` に `growth_rank_box` (JSON) を追加
- [x] `GrowthPreset` モデルを新カラム対応に更新
- [x] `Character` モデルに `growth_rank_box` を fillable/cast 追加

## ステップ29: ランク定義 & 抽選ロジック

- [x] `App\Services\Character\GrowthRank` 新設(RANKS / lower / next / key 組立 / initialBox)
- [x] `LevelUpService::advanceRank` を実装(抽選・維持・アップ・ダウン・境界処理)
- [x] `LevelUpService::grantExp` から `advanceRank` を呼ぶよう書換

## ステップ30: 設定

- [x] `config/arena.php` に `rank_box` セクション追加(initial_current/next, add_lower/next_on_rankup)

## ステップ31: シーダー

- [x] `GrowthPresetSeeder` を 4職+enemy × 5ランク の 25 プリセット自動生成に変更
- [x] `CharacterPresetSeeder` の `growth_preset_key` を `{job}_normal` 形式に更新(敵は easy/hard)
- [x] `JobSeekerSeeder` で Character 生成時に `growth_rank_box` を初期化

## ステップ32: テスト

- [x] `CreatesArenaFixtures::createJobSeeker` で `growth_rank_box` を初期化
- [x] `LevelUpServiceTest` を新 API で書き直し(維持/アップ/ダウン/easy境界/masterループ/抽選)
- [x] 全テスト緑化確認 (85 passed / 279 assertions)

## ステップ33: ドキュメント

- [x] `arena_v1_2_scope_ja.md` をランク抽選仕様に更新
- [x] README の成長プリセットセクションを v1.2.1 仕様に更新
- [x] `todo.md` に v1.2.1 セクション追加
