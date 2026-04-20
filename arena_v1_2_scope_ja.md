# 1v1 Arena v1.2 スコープ (キャラクターステータス拡張 & レベリング・確定版)

v1.1 の「家門 / 雇用 / ゲスト」モデルの上に、
キャラクターの**基本ステータス 4 種**と**成長プリセットによるレベリング**を追加する。

- 前提スコープ: `arena_v1_1_scope_ja.md`
- 戦闘コア (v1.0): `arena_v1_architecture_ja.md`

---

## 1. コンセプト

- キャラクターは **基本ステータス 4 種** を持ち、そこから **派生ステータス (HP/ATK/DEF)** を算出する。
- Lv アップは **固定の成長プリセット** に従って基本ステータスを加算する(ランダムではない)。
- 成長プリセットは **10 Lv ごとに次のプリセットに切り替わる**(サイクル固定)。
- 各プリセットの **10 番目の step は「壁」となる大幅上昇**(他 step の約 3 倍)で、必要 EXP も大きくなる。
- EXP は **バトル勝利時のみ** 獲得。敵 Lv に比例。
- 敵キャラクターも同じステータス体系で管理する(同じ `characters` テーブル構造相当を `character_presets` 側で保持)。

---

## 2. 基本ステータス (STR / VIT / DEX / INT)

| ステ | 別名 | 主な役割 |
|---|---|---|
| `str` | 力  | ATK 主要源・HP 補助 |
| `vit` | 体力 | HP 主要源・DEF 補助 |
| `dex` | 器用 | ATK / DEF 補助 |
| `int_stat` | 魔力 | 現状未使用(将来のスキル威力用に温存) |

> カラム名は MySQL 予約語回避のため `int_stat`。Vue では「魔」と表示。

---

## 3. 派生ステータス

`config/arena.php` の `derived_stats` に係数を集約:

```
HP_max = floor(STR * str_hp + VIT * vit_hp) + hp_const
ATK    = floor(STR * str_atk + DEX * dex_atk)
DEF    = floor(VIT * vit_def + DEX * dex_def)
```

デフォルト係数:

| 係数 | 値 |
|---|---|
| `hp_const` | 15 |
| `str_hp` | 1.0 |
| `vit_hp` | 2.0 |
| `str_atk` | 1.0 |
| `dex_atk` | 0.5 |
| `vit_def` | 0.5 |
| `dex_def` | 0.5 |

計算は純関数 `App\Services\Character\CharacterStats` に集約(`derive` / `forEntity` / `forPreset`)。

---

## 4. 成長プリセット & ランク抽選サイクル (v1.2.1)

### 4.1 データ構造 (`growth_presets`)

```
growth_presets
├─ id
├─ key           varchar UNIQUE     -- 形式: "{job}_{rank}" (例 warrior_normal)
├─ name          varchar            -- 表示用
├─ job           varchar            -- warrior / rogue / mage / priest / enemy
├─ rank          varchar            -- easy / normal / hard / expert / master
├─ rank_order    tinyint unsigned   -- 1=easy .. 5=master
├─ increments    json               -- 10 要素、各要素は {str, vit, dex, int_stat}
└─ created_at / updated_at
```

- `increments[9]` は **壁 step**(他の約 3 倍の上昇量)。Lv 10/20/30... が重くなる。
- 各ランクは職固有の配分 × ランク倍率 (`easy=0.7 / normal=1.0 / hard=1.4 / expert=1.8 / master=2.3`) で増分を生成。
- 合計 **4 職 + enemy × 5 ランク = 25 プリセット**。

### 4.2 プリセット key 命名規則

```
warrior_easy / warrior_normal / warrior_hard / warrior_expert / warrior_master
rogue_easy   / rogue_normal   / ... / rogue_master
mage_easy    / mage_normal    / ... / mage_master
priest_easy  / priest_normal  / ... / priest_master
enemy_easy   / enemy_normal   / ... / enemy_master
```

### 4.3 ランク抽選サイクル

10 Lv 消化時 (`growth_index == 10`) に **抽選箱** からランクを 1 つ引いて次のプリセットを決定する。

- **current** は今のプリセットのランク、**next** は `current` の次ランク(master の次は easy ループ)
- **初期抽選箱** = `current × initial_current + next × initial_next`
- **維持** (current を引いた): 箱から引いた分 1 つを除外するだけ(他はそのまま・シフトなし)
- **ランクアップ** (next を引いた): 箱内の **全ランクを 1 段シフトアップ** し、旧 current と新 next を加える。
  - `shift_up(引いた後の箱の残り) + 旧 current × add_lower_on_rankup + 新 current の次 × add_next_on_rankup`
  - 発想: current が 1 つ上がったので、箱内のランク表記を全員 +1 シフトして整合させる。旧 current は新 current から見ると下位に位置するので加算される。
- **ランクダウン** (下位を引いた): 箱内の **全ランクを 1 段シフトダウン** するのみ。追加はなし。
  - `shift_down(引いた後の箱の残り)`
- 境界:
  - ランクアップ時のシフト: master → easy(ループ)
  - ランクダウン時のシフト: easy → master(ループ)
  - easy の下位は null だが、シフトアップ方式では「旧 current」(例: master) が下位相当として追加される

例) `warrior_normal` から始まる場合 (初期箱 = `[normal, normal, hard]`):

```
ケース1: normal を引く (維持)
  新プリセット: warrior_normal
  次回箱: [normal, hard]   (引いた normal を 1 つ除外)

ケース2: hard を引く (ランクアップ)
  残り [normal, normal] → シフトアップ → [hard, hard]
  + 旧current(normal)×1 + 新next(expert)×1
  新プリセット: warrior_hard
  次回箱: [hard, hard, normal, expert]
```

例) 上の続き `warrior_hard` 箱=`[hard, hard, normal, expert]` から normal を引く (ランクダウン):

```
残り [hard, hard, expert] → シフトダウン → [normal, normal, hard]　(追加なし)
新プリセット: warrior_normal
次回箱: [normal, normal, hard]
```

例) `warrior_master` から easy を引いた場合 (初期箱 = `[master, master, easy]`):

```
残り [master, master] → シフトアップ(master→easy ループ) → [easy, easy]
+ 旧current(master)×1 + 新next(normal)×1
新プリセット: warrior_easy
次回箱: [easy, easy, master, normal]
```

### 4.4 敵プリセット

敵もプレイヤーと同じランク体系 (`enemy_easy` 〜 `enemy_master`)。通常は Lvup しないが、汎用的に扱えるよう 5 ランク用意。

---

## 5. 経験値 / レベルアップ

### 5.1 獲得 EXP

- 勝利時: `exp_gain = enemy_level * config('arena.leveling.exp_per_enemy_level')` (= 10)
- 敗北時・引き分け: 0

### 5.2 次 Lv 必要 EXP

```
必要EXP = (現在の成長プリセット.increments[growth_index] の 4 ステ合計)
        × config('arena.leveling.required_exp_per_stat_point')  (= 10)
```

- 通常 step (合計 ≈ 4〜5) → 必要 EXP ≈ 40〜50
- 壁 step (合計 ≈ 12〜17) → 必要 EXP ≈ 120〜170

### 5.3 グランド処理 (`LevelUpService::grantExp`)

1. `character.exp += amount`
2. ループで `exp >= requiredExpToNext()` の間 Lvup
   - `max_level_ups_per_battle` (= 5) でガード
   - `level >= max_level` (= 99) でストップ
   - 各 Lvup で `increments[growth_index]` を基本ステに加算・`exp -= required`・`level++`・`growth_index++`
   - `growth_index >= 10` になったら `growth_preset_key = next_preset_key` に切替、`growth_index = 0`
3. トランザクションで `character` を 1 回だけ保存

---

## 6. データモデル変更

### 6.1 `character_presets` (v1.1 スキーマを置換)

```diff
- hp_max / atk / def
+ base_str / base_vit / base_dex / base_int     -- 基本ステの初期値
+ base_level                                    -- 素体 Lv(既定 1)
+ growth_preset_key                             -- キャラ化したとき付与する成長プリセット
```

### 6.2 `characters` (v1.1 に追加カラム)

```diff
+ exp                   unsigned int default 0
+ str / vit / dex / int_stat  unsigned int -- 現在の基本ステ
+ growth_preset_key     varchar            -- 現在のプリセット key
+ growth_index          tinyint unsigned default 0   -- 0..9
+ growth_rank_box       json                 -- ランク抽選箱 (例: ["normal","normal","hard"])
```

HP は格納しない。派生ステは `CharacterStats::forEntity()` でその都度算出。

### 6.3 `battles` 変更なし

HP 初期値・現在 HP は従来通り `battles` 側で保持(`CharacterStats` 経由で初期化)。

---

## 7. 初期データ (Seeder)

### 7.1 `GrowthPresetSeeder`

- 4 職 (warrior/rogue/mage/priest) + enemy それぞれに 5 ランク (easy..master) の増分を自動生成
- 各 step は「職配分 × ランク倍率」でスケール、`increments[9]` は壁 step (通常の約 3 倍)
- 合計 25 プリセット

### 7.2 `CharacterPresetSeeder`

- プレイヤー素体: `hero_warrior` / `hero_rogue` / `hero_mage` / `hero_priest`
  - それぞれの `growth_preset_key` は自職の `normal` ランクで初期化 (例: `warrior_normal`)
- 敵プリセット: `enemy_goblin` (easy) / `enemy_ogre` (hard) / `enemy_slime` (easy)

### 7.3 `JobSeekerSeeder`

- Character 生成時、素体の `base_*` と `growth_preset_key` / `base_level` をコピー。
- 初期 `exp = 0`, `growth_index = 0`、`growth_rank_box = GrowthRank::initialBox(現ランク)`。

---

## 8. ドメイン / アプリ層

| クラス | 役割 |
|---|---|
| `App\Services\Character\CharacterStats` | 基本ステ→派生ステ純関数。`forEntity` / `forPreset` / `derive` |
| `App\Services\Character\GrowthRank` | ランク定義 / 上下/次の算出 / key 組立 / 初期抽選箱生成 |
| `App\Services\Character\LevelUpService` | EXP 加算・Lvup ループ・ランク抽選によるプリセット切替 (`advanceRank`) |
| `App\Models\GrowthPreset` | `incrementAt(int $index)` / job / rank / rank_order |

### 8.1 バトル側連携

- **`StartBattleAction`**: `CharacterStats::forEntity($character)` で `hp_max` を算出して `battles.player_hp_max` に保存。敵側も `CharacterStats::forPreset($enemyPreset)` で同様。
- **`ResolveTurnAction`**: 毎ターン `CharacterStats::forEntity/forPreset` で ATK/DEF を派生算出し `DamageCalculator::resolve(..., playerAtk, playerDef, enemyAtk, enemyDef)` に渡す。決着時に `winner === player` なら `LevelUpService::grantExp($playerCharacter, LevelUpService::rewardExpFromEnemyLevel($enemyLevel))` を実行。Lvup 結果をログに含める(任意)。
- **`DamageCalculator`**: ダメージ = `max(min_damage, floor(attacker.ATK * multiplier) - defender.DEF)`。係数は `config('arena.damage')` (weak=1.0 / strong=2.0 / counter=1.5 / min=1)。

### 8.2 UI 連携

- 求職者カード / 家門ダッシュボード / バトル画面で以下を表示:
  - **Lv** / **EXP**
  - 基本ステ **力 / 体 / 器 / 魔**
  - 派生ステ **HP / ATK / DEF**
- 既存の `BattleStatusPanel` に `level` / `stats (str/vit/dex/int/atk/def)` props を追加。

---

## 9. 設定値 (`config/arena.php`)

```php
'derived_stats' => [
    'hp_const' => 15,
    'str_hp' => 1.0, 'vit_hp' => 2.0,
    'str_atk' => 1.0, 'dex_atk' => 0.5,
    'vit_def' => 0.5, 'dex_def' => 0.5,
],

'leveling' => [
    'exp_per_enemy_level'         => 10,
    'required_exp_per_stat_point' => 10,
    'max_level'                   => 99,
    'max_level_ups_per_battle'    => 5,
],

'rank_box' => [
    'initial_current'      => 2,  // 初期箱の現在ランク個数
    'initial_next'         => 1,  // 初期箱の次ランク個数
    'add_lower_on_rankup'  => 1,  // ランクアップ時に追加する下位ランク数
    'add_next_on_rankup'   => 1,  // ランクアップ時に追加する次ランク数
],
```

---

## 10. テスト方針

- **Unit**: `CharacterStatsTest` — 基本ケース + 境界(0 ステ・大きな値)の派生計算。
- **Feature**: `LevelUpServiceTest`
  - 必要 EXP 計算(上昇値合計 × 係数)
  - EXP 付与による Lvup と基本ステ加算
  - 初期抽選箱の構成
  - ランク維持 / ランクアップ (下位・次の追加) / ランクダウン の各パターン
  - easy からのランクアップ / master → easy ループの境界挙動
  - 10 Lv 消化時に抽選が走り、同職の有効ランクに切り替わる
  - 敵 Lv と獲得 EXP の関係
- 既存の Battle / Hiring / Reward テストは新 API (CharacterStats 由来 HP) に合わせて修正。

---

## 11. v1.2 スコープ外(やらない)

- `int_stat` を使ったスキル威力 / 魔法攻撃(仕組みとして温存)
- 家門レベル上昇(雇用枠変動)
- 装備・アイテム・属性
- 職替え(現状のランク抽選は同一職内でのみ遷移する)
