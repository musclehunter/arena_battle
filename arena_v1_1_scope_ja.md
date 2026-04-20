# 1v1 Arena v1.1 スコープ (家門 & 雇用モデル・確定版)

ユーザーの追加決定を反映した確定スコープ。未決事項は推奨案で確定してある。

---

## 1. コンセプト

- **家門 (House)**: プレイヤーの組織。ユーザーアカウントに紐づく。
- **キャラクター (Character)**: アリーナで戦う個人。家門に雇用される。雇用されていない = 求職者プール在籍。
- **通貨 (Gold)**: 雇用・報酬の基軸。勝利で得て、契約に従い家門とキャラに分配。

### プレイヤー形態

| 形態 | 認証 | 家門 | 雇用の継続性 |
|---|---|---|---|
| **ゲスト** | 不要 | なし | バトル終了で自動解雇 |
| **家門プレイヤー** | 必要 | あり (1ユーザー1家門) | 継続雇用 |

家門プレイヤーも **ゲスト雇用 (単発雇用)** は可能(家門の雇用枠を消費しない)。

---

## 2. データモデル (確定)

### 2.1 `houses` (新)

```
houses
├─ id            bigint PK
├─ user_id       bigint UNIQUE FK → users.id (cascade delete)
├─ name          varchar(24)   -- 重複OK
├─ level         unsigned int  default 1
├─ gold          unsigned int  default 1000
├─ created_at / updated_at
```

- 1 ユーザー = 1 家門
- Lv1 雇用上限 = 3 人 (`config/arena.php` に定数化)

### 2.2 `characters` (新) - 求職者と雇用中を同居

```
characters
├─ id                  bigint PK
├─ character_preset_id bigint FK → character_presets.id   -- 能力テンプレ
├─ name                varchar(32)        -- seed で固有名、重複OK
├─ level               unsigned int default 1
├─ hire_cost           unsigned int       -- 家門での契約金(ベース)
├─ reward_share_bp     unsigned int       -- キャラ取り分 (basis points)
├─ gold                unsigned int default 50   -- キャラ自身の蓄財 (初期50)
├─ house_id            bigint NULL FK → houses.id   -- NULL=求職者 / guest_house=ゲスト雇用 / 他=家門雇用
├─ hired_at            timestamp NULL
├─ created_at / updated_at

INDEX (house_id)
INDEX (character_preset_id)
```

#### `house_id` のセマンティクス(確定)

| 値 | 意味 |
|---|---|
| `NULL` | **求職者プール在籍** |
| `config('arena.guest_house_id')` | **ゲスト雇用中**(共通の「ゲスト家門」に一時所属) |
| その他の値 | **家門に正規雇用中** |

- `house_id` は **NULL 許容 + FK を `houses.id` に張る**。
- 「ゲスト家門」= seeder で先頭に作成されるダミー家門(ダミー user に紐づく)。
- ゲスト家門の id は **自動採番の結果を `config/arena.php` に静的に記録**(本スコープでは `1` を想定)。
- 複数ゲストが同時にゲスト雇用しても、全員が **同じ guest_house に所属**する。個別識別は `battles.guest_session_id` 側で行う。
- ゲスト家門自体の `gold` 値は使わない(ゲストの gold はセッション保存のため)。

### 2.3 `character_presets` (既存・役割変更)

- v1.0 までは「プレイヤー用・敵用」両方を兼ねていた
- v1.1 以降は **キャラクターの能力テンプレ (master data)** として使う
- 敵は `enemy_basic` を使い続ける
- 複数キャラ (`characters`) が **同一 preset を重複参照可**

### 2.4 `battles` (変更)

```
battles
├─ id
├─ user_id                 NULL   -- ゲスト時 NULL
├─ house_id                NULL   -- ゲスト or 家門未所属時 NULL
├─ guest_session_id        varchar(64) NULL   -- ゲスト識別
├─ player_character_id     NOT NULL FK → characters.id
├─ enemy_preset_id         NOT NULL FK → character_presets.id
├─ player_hp, enemy_hp, turn_number, status, winner, action_token
├─ started_at, ended_at
├─ reward_gold_total         unsigned int NULL  -- 勝利時のみ値あり
├─ reward_gold_to_character  unsigned int NULL
├─ reward_gold_to_house      unsigned int NULL
└─ created_at / updated_at
```

- `house_id IS NULL` かつ `guest_session_id IS NOT NULL` → ゲストバトル
- `house_id IS NOT NULL` → 家門バトル
- ゲスト雇用でも家門プレイヤーは `battles.house_id` に自家門を入れる(キャラ側 `characters.house_id` はゲスト家門、`battles.house_id` は実家門、と役割を分ける)

### 2.5 ER 図

```
users 1 ── 0..1 houses
houses 1 ── * characters (house_id = 家門ID)
character_presets 1 ── * characters (能力テンプレ)
characters 1 ── * battles (player)
character_presets 1 ── * battles (enemy)
houses 1 ── * battles
battles 1 ── * battle_logs
```

---

## 3. ゲスト・家門・キャラの状態遷移

### 3.1 キャラの `house_id` 遷移

```
[求職者: house_id=NULL]
    │家門雇用 (HireAction)
    ▼
[家門雇用中: house_id=家門ID]
    │解雇 (ReleaseAction)
    ▼
[求職者: house_id=NULL]
```

```
[求職者: house_id=NULL]
    │ゲスト雇用 (GuestHireAction)
    ▼
[ゲスト雇用中: house_id=guest_house_id]
    │バトル完了後に自動解雇
    ▼
[求職者: house_id=NULL]
```

### 3.2 ゲストセッションの状態

セッションに以下を保存(Redis):

```
guest = {
    session_id: string,                 -- Laravelの session id を流用
    gold: int (default 1000),
    hired_character_id: int | null,     -- ゲスト雇用中のキャラid
    job_seeker_ids: int[] | null,       -- 現在表示中の3名の求職者リスト
}
```

- 家門作成時に **gold は破棄**、家門 gold=1000 スタート
- `hired_character_id` が set されているときのみ `house_id=guest_house_id` のキャラが存在する保証

---

## 4. 通貨ルール(確定)

| 項目 | 値 |
|---|---|
| ゲストの初期 gold | **1000** |
| 家門作成時の初期 gold | **1000**(ゲスト gold は破棄) |
| 求職者キャラの初期 gold | **50** |
| 勝利報酬 (total) | **200** (`config/arena.php::reward.win_total`) |
| 敗北・引分時の報酬 | **0** |
| ゲスト雇用の割増 | **`hire_cost` × 1.5**(切り上げ) (`config/arena.php::guest_hire_multiplier`) |
| 家門 Lv1 雇用上限 | **3 人** |

### 4.1 報酬分配

勝利時のみ発生:

```
reward_total = 200                                  // 固定
reward_to_character = reward_total * reward_share_bp / 10000
reward_to_house     = reward_total - reward_to_character   // 合計は常に total
```

- ゲスト雇用時は `reward_to_house` は **ゲストセッションの gold に加算**
- 家門雇用時は `reward_to_house` は家門の gold に加算
- `reward_to_character` は **常にキャラ本人の gold に加算** (ゲスト雇用でも本人に入る)

### 4.2 解雇時の gold(確定)

- **キャラの gold はそのまま保持**(持ち逃げ OK)
- 再雇用時も gold 残高は維持される
- 家門への譲渡などは行わない

### 4.3 ゲスト雇用の契約金(確定)

- `guest_hire_cost = ceil(character.hire_cost * 1.5)`
- 家門雇用のみ `character.hire_cost` そのまま
- 不足時は雇用不可(UI 側 disable + サーバ側検証)

---

## 5. 求職者 (Job Seeker) 仕様

### 5.1 プール生成 (Seeder)

- `JobSeekerSeeder` で 10 人の `characters` を生成
- `house_id = NULL` (求職者)
- `name`: 固有名詞固定リストから
- `character_preset_id`: `character_presets` からランダム選択(**重複利用可**)
- `level = 1` (v1.1 は全員Lv1)
- `gold = 50`
- `hire_cost` / `reward_share_bp` は下記式で算出(**seed 時に確定、以後不変**)

```
hire_cost       = round(100 * level * (1 + random(0.0, 0.3)))
reward_share_bp = 3000 + (level - 1) * 500 + random_int(-300, 300)
// Lv1 → 取り分 約27〜33%
```

マジックナンバーは `config/arena.php::job_seeker.*` に置く。

### 5.2 表示される 3 名(確定)

- セッションに `job_seeker_ids: int[]` を保存
- 未設定の場合、`house_id IS NULL` かつ **ロック対象でない** キャラから **ランダム 3 名** 選出してセッションに書き込む
- **更新タイミング(確定)**:
  - **アリーナバトル完了時にリストを破棄** → 次回訪問時に再抽選
  - リスト内の誰かが雇用された場合は、そのまま残り 2 名で表示(穴埋めしない)
  - (毎ページ訪問での再抽選はしない)
- この挙動は家門プレイヤー / ゲスト 共通(セッションで保持)

### 5.3 求職者のロック(= 表示から除外する条件)

求職者リスト生成時、以下のキャラは除外:
- `house_id IS NOT NULL` (誰かに雇用中)
- `house_id IS NULL` だが **未完了の battle に関与中**(ゲスト雇用の途中で bloat したレコード救済)

```sql
-- 候補 = house_id IS NULL AND NOT EXISTS(
--   SELECT 1 FROM battles b
--   WHERE b.player_character_id = characters.id
--   AND b.status = 'in_progress'
-- )
```

---

## 6. 画面フロー

```
ゲスト:
 /              → 所持gold表示 + [求職者を見る]
 /job-seekers   → 3枚カード: 雇用ボタン2種 ([ゲスト雇用して挑む])
                 (未ログインなら「ゲスト雇用して挑む」のみ)
                 ※ゲスト雇用すると即バトル開始
 /battles/{id}  → v1と同じ

家門プレイヤー:
 /              → ログイン済みなら /houses/mine にリダイレクト
 /houses/mine   → 家門ダッシュボード
                   - 家門名 / Lv / gold / 雇用枠 X/3
                   - 雇用キャラ一覧
                     [この子で挑む] [解雇]
                   - [求職者を見る]
 /job-seekers   → 3枚カード:
                   [家門で雇用する]        (gold不足/枠超過で disabled)
                   [ゲスト雇用して挑む]    (家門プレイヤーも可)
 /houses/create → 家門未作成時。家門名を入力
 /battles/{id}  → v1と同じ + 報酬表示(勝利時)
```

### 6.1 ルーティング

| メソッド | パス | 認証 |
|---|---|---|
| GET | `/` | 任意(ログイン済みなら /houses/mine へ) |
| GET | `/job-seekers` | 任意 |
| POST | `/houses` | 必須 (家門作成) |
| GET | `/houses/mine` | 必須 |
| POST | `/houses/hire` | 必須 (家門雇用) |
| POST | `/houses/release/{character}` | 必須 (解雇) |
| POST | `/guest-hires` | 任意 (ゲスト雇用 → バトル自動開始) |
| POST | `/battles/{battle}/turn` | 任意 (所有者のみ) |
| POST | `/battles/{battle}/restart` | 任意 (同一キャラで再戦) |
| GET | `/battles/{battle}` | 任意 (所有者のみ) |

### 6.2 同時進行バトル 1 件制限(確定)

- 家門プレイヤー: `battles` に `status=in_progress` のものがあれば **新規開始を 422 で拒否**(「進行中のバトルがあります」リンク)
- ゲスト: `guest_session_id` ベースで同様

---

## 7. 実装層

### 7.1 Domain Services

- `JobSeekerBoard` — セッション保存の3名管理(取得/再抽選/無効化)
- `HiringService`
  - `hireByHouse(House, Character)` — 契約金支払い + `house_id` 更新
  - `hireAsGuest(GuestContext, Character)` — `hire_cost * 1.5` 支払い + `house_id=guest_house_id`
  - `release(House, Character)` — `house_id=NULL` に戻す(gold 保持)
  - `autoReleaseAfterGuestBattle(Battle)` — バトル完了時 `house_id=NULL`
- `GuestContext` — セッションの gold / hired_character_id 管理
- `RewardDistributor` — 勝利時に報酬を分配

### 7.2 Actions

- `CreateHouseAction` — 家門作成
- `HireCharacterAction` / `GuestHireCharacterAction` / `ReleaseCharacterAction`
- `StartBattleAction` (改修) — `Character` を受け取り、`is_guest_hire` 相当の判別を行って battle レコード作成
- `ResolveTurnAction` (改修) — 決着時に `RewardDistributor` を呼ぶ、ゲスト雇用なら `autoReleaseAfterGuestBattle` を呼ぶ
- `RestartBattleAction` (改修) — 家門雇用中キャラは同キャラで再戦 / ゲスト雇用は新規雇用扱い(都度契約)

### 7.3 Controllers

- `HouseController` (create/store/show)
- `JobSeekerController` (index)
- `CharacterController` (release)
- `GuestHireController` (store = 雇用 + バトル開始)
- `BattleController` (改修: 所有者認可、ゲストは session ベース)

### 7.4 Policies

- `BattlePolicy` — `user_id == 自分` または `guest_session_id == 自セッション`
- `HousePolicy` — 自家門のみ操作可
- `CharacterPolicy` — 解雇は `house_id == 自家門`

---

## 8. 設定値 `config/arena.php`

```php
return [
    'house_level_slots' => [1 => 3],   // Lv => 雇用上限
    'initial_gold' => [
        'guest' => 1000,
        'house' => 1000,
        'character' => 50,
    ],
    'reward' => [
        'win_total' => 200,
        'lose_total' => 0,
    ],
    'guest_hire_multiplier' => 1.5,
    'job_seeker' => [
        'pool_size' => 10,
        'visible_count' => 3,
        'hire_cost_base' => 100,
        'hire_cost_variance' => 0.3,
        'share_bp_base' => 3000,
        'share_bp_per_level' => 500,
        'share_bp_variance' => 300,
    ],
    'guest_house_id' => 1,   // ゲスト家門の id (seeder の先頭で作成される前提)
    'system_user_id' => 1,   // ゲスト家門を所有するダミー user の id
];
```

---

## 9. Seeder 戦略

`DatabaseSeeder` に以下の順で登録する(順序重要)。

1. **`SystemAccountSeeder` (新)** — ダミー user を `updateOrCreate(['email' => 'system@arena.local'], ...)` で作成。
   - `name = 'System'`, `password = Hash::make(random_bytes(32))` (ログイン不能な実用環境向け), `email_verified_at = null`
   - 作成後の id が `config('arena.system_user_id')` (= 1) と合うことを sanity check して落とす
2. **`GuestHouseSeeder` (新)** — ダミー user に紐づく `houses` レコードを作成。
   - `name = 'Guest House'`, `level = 1`, `gold = 0`
   - 作成後の id が `config('arena.guest_house_id')` (= 1) と合うことを sanity check
3. **`CharacterPresetSeeder`** — 現行踏襲(将来プリセット種類が増える前提)
4. **`JobSeekerSeeder` (新)** — `pool_size=10` の `characters` を生成。`house_id=NULL` (求職者内蔵)

**sanity check** のイメージ:

```php
if ((int) $dummy->id !== config('arena.system_user_id')) {
    throw new RuntimeException('SystemAccountSeeder: id が config と不一致。migrate:fresh してやり直してください。');
}
```

これで id がずれた場合は即時に気づける。

---

## 10. 非スコープ (v1.1 ではやらない)

- レベルアップ / 経験値
- 敵バリエーション / 難易度
- PvP
- 求職者の時間経過入替(§5.2 は「バトル完了時に再抽選」固定)
- 改名機能
- 装備・スキル
- 戦績集計
- ゲスト永続化

---

## 11. リスク・懸念

### 11.1 ゲスト家門の取り扱い

- `characters.house_id` は **NULL 許容 + FK を張る** ので、整合性は DB が保証する。
- ただし「家門の雇用人数を数える」などの集計時、**ゲスト家門を除外** する必要がある。
- スコープ用のグローバルスコープ or モデルヘルパーでカバー:
  ```php
  // Character モデル
  public function isGuestHired(): bool { return $this->house_id === config('arena.guest_house_id'); }
  public function isAvailable(): bool  { return $this->house_id === null; }
  public function isEmployedByPlayerHouse(): bool {
      return $this->house_id !== null && $this->house_id !== config('arena.guest_house_id');
  }

  // House モデル: ゲスト家門を扱わないスコープ
  public function scopePlayerOwned(Builder $q): Builder {
      return $q->where('id', '!=', config('arena.guest_house_id'));
  }
  ```
- 「家門の雇用人数」は `characters()->count()` だが、ゲスト家門は `houses` 一覧で除外表示されるべき。

### 11.2 ゲスト雇用中にセッションが切れた場合

- `house_id = guest_house_id` のまま孤立キャラが残る可能性
- 対策: バトル完了時に必ず `house_id=NULL` に戻す処理(冪等)
- 保険: 求職者候補の絞り込みで `in_progress バトルなし` を条件に入れるため、孤立しても実害は薄い
- v1.2 以降で TTL クリーンアップを検討

### 11.3 ゲスト/家門の同時バトル1件制限

- ゲスト: `WHERE guest_session_id=? AND status='in_progress'`
- 家門: `WHERE user_id=? AND status='in_progress'`
- 家門プレイヤーが「ゲスト雇用してバトル中」の場合: `user_id` 検索のほうで引っかかるため家門バトルも開始不可(想定どおり)

### 11.4 報酬計算の整合性

- `reward_share_bp` が 0 や 10000 を超える値にならないこと
- Seeder の `random` 範囲で担保 + `clamp(share_bp, 100, 9000)` でサーバ側も保険

### 11.5 求職者リスト更新タイミング

- 「バトル完了時に破棄」= ゲスト雇用 → バトル → 完了 のループでは毎回新しい 3 名が出る
- 家門雇用 (バトルを挟まない) の場合はリストが変わらない → 雇用された分だけ減る
- これは §5.2 の明示ルール。UX 的に違和感があれば v1.2 で「家門で雇用したときも再抽選」に変える余地あり

---

## 12. 決定事項サマリ

| 項目 | 確定値 |
|---|---|
| §2.2 ゲスト雇用の表現 | `characters.house_id = config('arena.guest_house_id')` (seeder のダミー家門) |
| §2.2 `characters.house_id` | NULL 許容 + FK あり / NULL=求職者 |
| §2.2 キャラ初期gold | 50 |
| §4 ゲスト初期gold | 1000 (セッション保存) |
| §4 家門初期gold | 1000 (作成時にゲストgold破棄) |
| §4 勝利報酬 | 200 固定 / 敗北 0 |
| §4 ゲスト雇用割増 | hire_cost × 1.5 |
| §4.2 解雇時のキャラgold | 保持 |
| §5 求職者プール | 10 名 / 表示 3 名 |
| §5.2 リスト更新 | アリーナバトル完了時 |
| §6.2 同時進行バトル | 1 件制限(超過は 422) |
| §7.11(旧) キャラLv | 全員 Lv1 |
| §7.15(旧) 家門名 | 重複 OK / 1〜24 文字 |
| §7.15(旧) キャラ名 | 重複 OK / Seeder 固定 |

---

## 13. 次のアクション

このスコープで問題なければ `todo.md` に v1.1 実装タスクを起こします。

ざっくり順序:

1. `config/arena.php` と定数整備
2. `houses` / `characters` マイグレーション + モデル
3. `battles` マイグレーション改修
4. Seeder 整備(プリセット拡張は任意、JobSeekerSeeder は必須)
5. `JobSeekerBoard` / `GuestContext` / `HiringService`
6. `StartBattleAction` / `ResolveTurnAction` 改修
7. コントローラ / ポリシー
8. Vue ページ (JobSeeker 一覧、家門作成、家門ダッシュボード、バトル画面の報酬表示追加)
9. Feature テスト一式
10. README 更新
