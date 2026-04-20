# 1v1アリーナ v1 設計メモ

## 1. 目的

この文書は、先に作成した v1 仕様をもとに、実装を始めるための具体設計をまとめたものです。

今回固める対象は以下です。

1. DB設計
2. 画面ワイヤー
3. Laravel のディレクトリ設計
4. 実装順序

---

## 2. 前提

- バックエンド: Laravel
- フロントエンド: Inertia.js + Vue 3
- CSS: Tailwind CSS
- DB: MySQL
- Redis: キャッシュ・セッション
- v1 は 1vs1 のターン制バトル
- テキスト中心
- ランダム要素は最小
- まずは CPU 戦のみ

---

## 3. DB設計

## 3.1 設計方針

v1 では「拡張しやすいこと」を優先しつつ、テーブル数は増やしすぎないようにします。

まずは以下の 4 テーブルで十分です。

1. users
2. character_presets
3. battles
4. battle_logs

### 3.1.1 なぜ装備テーブルを作らないのか

v1 では装備を固定にするためです。

装備や所持品を最初から一般化すると、完成が遠のきます。まずはキャラクターの初期ステータスを preset として持つだけで十分です。

---

## 3.2 users

Laravel 標準の users を利用。

主な利用目的:

- 認証
- 対戦履歴の所有者

追加が必要なら以下程度。

- name
- current_rating（将来用、なくてもよい）

---

## 3.3 character_presets

プレイヤーや敵の初期能力値セットを定義するテーブル。

### カラム案

- id
- key
- name
- hp
- attack
- defense
- ai_type nullable
- is_enemy
- created_at
- updated_at

### 用途

- プレイヤー固定ステータス
- 敵固定ステータス
- 将来の職業選択やテンプレ差し替え

### 例

- `player_basic`
- `enemy_basic`
- `enemy_guard`

---

## 3.4 battles

1回の対戦そのものを表すテーブル。

### カラム案

- id
- user_id nullable
- player_preset_id
- enemy_preset_id
- status
- winner
- turn_number
- player_hp
- enemy_hp
- started_at
- ended_at nullable
- created_at
- updated_at

### カラム補足

#### status

想定値:

- `in_progress`
- `finished`

#### winner

想定値:

- `player`
- `enemy`
- `draw`
- `null`（対戦中）

#### turn_number

現在のターン数。

#### player_hp / enemy_hp

現在値を保持。

初期値は preset からコピーして保存する。

理由:

- 対戦中の状態を毎回再計算しなくてよい
- 履歴の再現がしやすい

#### 状態フラグについて

v1 のコマンドは「弱攻撃 / 強攻撃 / カウンター攻撃」の 3 種類で、
**ターンをまたいで持続する状態(防御中・チャージ中など)は存在しません**。

---

## 3.5 battle_logs

ターンごとのログを保存するテーブル。

### カラム案

- id
- battle_id
- turn_number
- player_action
- enemy_action
- player_damage_to_enemy
- enemy_damage_to_player
- player_hp_after
- enemy_hp_after
- summary_text
- created_at
- updated_at

### 方針

v1 では **1ターン = 1レコード** とし、そのターン内で発生した複数行のメッセージは
`summary_text` に改行区切りで詰め込む簡易方式を採用します。

`summary_text` の例(改行は `\n`):

```text
3ターン目: あなたは強攻撃した。
3ターン目: 敵はカウンターした。
3ターン目: カウンター成功。あなたは6ダメージを受けた。
```

高度なリプレイや統計が必要になった段階で別テーブルまたは JSON カラムを追加します。

この方針の利点:

- 画面表示がそのまま `summary_text` を流し込むだけで済む
- スキーマがシンプルで、v1 の実装速度が上がる

---

## 3.6 最小ERイメージ

- users 1 --- n battles
- character_presets 1 --- n battles（player_preset_id）
- character_presets 1 --- n battles（enemy_preset_id）
- battles 1 --- n battle_logs

---

## 4. 画面設計

## 4.1 v1 で必要な画面

最小で以下の 2 画面です。

1. バトル画面
2. 結果表示

ただし実際には結果表示はバトル画面内に含めてよいです。

つまり、実質 1 画面でも成立します。

---

## 4.2 画面ワイヤー

## 4.2.1 バトル画面

```text
+--------------------------------------------------+
| 1v1 Arena                                        |
+--------------------------------------------------+
| Player                              Enemy         |
| Name: You                           Name: Goblin |
| HP  : 30 / 30                       HP  : 30 /30 |
| State: Normal                       State: Normal|
+--------------------------------------------------+
| Turn: 1        Battle State: selecting           |
+--------------------------------------------------+
| Battle Log                                        |
|--------------------------------------------------|
| Turn 1: Battle started.                          |
| Turn 1: Choose your action.                      |
|                                                  |
|                                                  |
+--------------------------------------------------+
| [弱攻撃]   [強攻撃]   [カウンター]                 |
+--------------------------------------------------+
```

### 意図

- 上: 状態確認
- 中: ログ
- 下: 行動

ゲームとしてもデバッグ画面としても成立する構成です。

---

## 4.2.2 決着後の状態

```text
+--------------------------------------------------+
| Result                                           |
+--------------------------------------------------+
| Victory                                          |
| Final HP: You 12 / Enemy 0                       |
|                                                  |
| [Play Again]   [Back to Top(optional)]           |
+--------------------------------------------------+
```

v1 ではモーダルでも、ログの下に結果パネルを表示でも構いません。

---

## 4.3 コンポーネント分割案

Vue 側は細かく分けすぎず、以下で十分です。

### 画面

- `Pages/Battle/Show.vue`

### 部品

- `Components/BattleStatusPanel.vue`
- `Components/BattleLogPanel.vue`
- `Components/BattleActionButtons.vue`
- `Components/BattleResultPanel.vue`

### 理由

- 分割しすぎると追いにくい
- ただし 1 ファイル巨大化は避ける
- UI責務ごとに区切る

---

## 5. Laravel ディレクトリ設計

## 5.1 方針

Controller にロジックを寄せない。

戦闘ロジックは後で最も重要なコアになるため、アプリケーション層・ドメイン層に寄せます。

---

## 5.2 ディレクトリ案

```text
app/
├─ Actions/
│  └─ Battle/
│     ├─ StartBattleAction.php
│     ├─ ResolveTurnAction.php
│     └─ RestartBattleAction.php
├─ Data/
│  └─ Battle/
│     ├─ BattleStateData.php
│     └─ TurnResultData.php
├─ Enums/
│  ├─ BattleActionType.php
│  ├─ BattleStatus.php
│  └─ BattleWinner.php
├─ Http/
│  ├─ Controllers/
│  │  └─ BattleController.php
│  └─ Requests/
│     └─ Battle/
│        ├─ StartBattleRequest.php
│        └─ SubmitBattleActionRequest.php
├─ Models/
│  ├─ Battle.php
│  ├─ BattleLog.php
│  └─ CharacterPreset.php
├─ Services/
│  └─ Battle/
│     ├─ BattleManager.php
│     ├─ EnemyActionDecider.php
│     ├─ DamageCalculator.php
│     └─ BattleLogFactory.php
└─ Support/
   └─ Battle/
      └─ BattleStateMapper.php
```

---

## 5.3 各役割

### BattleController

責務:

- HTTP リクエスト受付
- Action 呼び出し
- Inertia レスポンス返却

ここにダメージ計算は書かない。

---

### StartBattleAction

責務:

- プレイヤー preset 決定
- 敵 preset 決定
- battles レコード作成
- 初期ログ作成

---

### ResolveTurnAction

責務:

- 入力アクション受け取り
- 敵行動決定
- ターン解決
- HP 更新
- フラグ更新
- ログ保存
- 勝敗判定

実質 v1 の最重要クラスです。

---

### RestartBattleAction

責務:

- 既存 battle を元に新規 battle を作る
- または preset を元に再戦を作る

v1 では「新しく battle を作り直す」で十分です。

---

### EnemyActionDecider

責務:

- 敵の行動を決める

最初は固定パターンでよいです。

例:

- 1ターン目 weak
- 2ターン目 strong
- 3ターン目 counter
- 4ターン目 weak

---

### DamageCalculator

責務:

- ダメージ計算を行う
- 防御やチャージ補正を適用する

数式変更時の影響範囲を局所化できます。

---

### BattleLogFactory

責務:

- ログ保存用データ生成
- 画面表示用 summary_text 作成

---

## 6. Enum 設計

文字列の直書きを減らすため、Enum を使うのがよいです。

### BattleActionType

- weak
- strong
- counter

### BattleStatus

- in_progress
- finished

### BattleWinner

- player
- enemy
- draw

---

## 7. ルーティング案

```php
Route::get('/', [BattleController::class, 'index'])->name('home');
Route::post('/battles', [BattleController::class, 'store'])->name('battles.store');
Route::get('/battles/{battle}', [BattleController::class, 'show'])->name('battles.show');
Route::post('/battles/{battle}/turn', [BattleController::class, 'resolveTurn'])->name('battles.turn');
Route::post('/battles/{battle}/restart', [BattleController::class, 'restart'])->name('battles.restart');
```

### 補足

トップ画面を省略するなら、 `/` にアクセスしたら「対戦開始ボタンだけある画面」でもよいし、即 battle 作成でもよいです。

ただし開発中は `store` を明示した方が追いやすいです。

---

## 7.5 ターン解決フロー

v1 では **プレイヤー先行入力 → サーバで敵行動決定 → 同時解決 → 結果を一括返却** の流れを採用します。

### シーケンス

1. プレイヤーが画面で行動を選択し、 `POST /battles/{battle}/turn` を送信する
2. サーバ側で `EnemyActionDecider` が敵の行動を決定する
3. `DamageCalculator` が両者の行動を同時に解決し、ダメージを算出する
4. `battles` の HP・ターン数・ステータスを更新する
5. そのターンで発生したメッセージ群を 1 件の `battle_logs` レコード(`summary_text` に改行区切り)として保存する
6. 更新後の `battles` と追加された `battle_logs` を Inertia レスポンスで返す

### ログの表示順ルール

`summary_text` に詰めるメッセージは以下の順で固定します。

1. あなた(プレイヤー)の行動宣言
2. 敵の行動宣言
3. 解決結果(カウンター成否・ダメージ・HP 変化など)

これにより、プレイヤーから見て「自分が何をして、敵が何をして、結果どうなったか」が常に同じ並びで読めます。

### 不正・二重送信対策

`SubmitBattleActionRequest` で以下を検証します。

- 対象 battle の `status` が `in_progress` であること
- token が一致すること

これで「決着後の送信」「連打による二重解決」を防ぎます。

---

## 8. 実装順序

## 8.1 ステップ1: DBとモデル

まず作るもの:

- migrations
- Battle model
- BattleLog model
- CharacterPreset model
- seeders

### seed 例

- player_basic
- enemy_basic

ここで DB に初期データを入れる。

---

## 8.2 ステップ2: 静的画面

作るもの:

- `Show.vue`
- ステータスパネル
- ログパネル
- ボタンUI

この時点ではハードコードでもよいです。

目的は「見た目の器を作ること」です。

---

## 8.3 ステップ3: バトル開始

作るもの:

- StartBattleAction
- BattleController@store
- battle 作成処理
- 初期ログ作成

この時点で `/battles/{id}` が表示できるようにする。

---

## 8.4 ステップ4: 1ターン解決

作るもの:

- ResolveTurnAction
- EnemyActionDecider
- DamageCalculator
- BattleLogFactory

この時点で、1回ボタンを押すと 1 ターン進むようにする。

---

## 8.5 ステップ5: 勝敗判定

追加するもの:

- HP 0 以下で終了
- status 更新
- winner 更新
- 結果パネル表示

---

## 8.6 ステップ6: 再戦

追加するもの:

- restart endpoint
- Play Again ボタン

これで「繰り返し遊べる最低限」が成立します。

---

## 9. v1 のダメージ計算のたたき台

今回の v1 では、コマンドを以下の 3 つに変更します。

- 弱攻撃
- 強攻撃
- カウンター攻撃

この 3 つで、読み合いが成立するシンプルな関係を作ります。

### 9.1 コマンド仕様

#### 弱攻撃

- 基本攻撃
- カウンターされない
- 安定してダメージを与えられる

#### 強攻撃

- 弱攻撃の 2 倍の威力
- カウンターされるとダメージを与えられない
- ハイリスク・ハイリターン

#### カウンター攻撃

- 相手が弱攻撃ならカウンター失敗
- カウンター失敗時は相手の弱攻撃ダメージをそのまま受ける
- 相手が強攻撃ならカウンター成功
- カウンター成功時は相手に「弱攻撃の 1.5 倍」のダメージを与える
- 相手の強攻撃は不発になる

---

### 9.2 相性

関係は以下です。

- 弱攻撃 → カウンターに強い
- 強攻撃 → 弱攻撃に強い
- カウンター攻撃 → 強攻撃に強い

つまり、ほぼ 3 すくみになります。

---

### 9.3 基本値例

最初はかなり単純でよいです。

- Player HP: 30
- Enemy HP: 30
- 弱攻撃ダメージ: 4
- 強攻撃ダメージ: 8
- カウンター成功ダメージ: 6

このとき、

- 強攻撃 = 弱攻撃の 2 倍
- カウンター成功 = 弱攻撃の 1.5 倍

となります。

---

### 9.4 解決ルール

同一ターンで、プレイヤーと敵が同時にコマンドを出したものとして解決します。

#### 弱攻撃 vs 弱攻撃

- 両者が弱攻撃ダメージを受ける

#### 弱攻撃 vs 強攻撃

- 両者がそれぞれの攻撃ダメージを与える

#### 弱攻撃 vs カウンター攻撃

- 弱攻撃側が弱攻撃ダメージを与える
- カウンター側は失敗し、ダメージを与えられない

#### 強攻撃 vs 強攻撃

- 両者が強攻撃ダメージを受ける

#### 強攻撃 vs カウンター攻撃

- 強攻撃側の攻撃は無効
- カウンター側が成功し、弱攻撃の 1.5 倍ダメージを与える

#### カウンター攻撃 vs カウンター攻撃

- 両者とも不発
- ダメージなし

実装時は左右対称になるよう、プレイヤー視点と敵視点で同じルールを適用します。

---

### 9.5 設計上の利点

この仕様の利点は以下です。

- コマンドが 3 つだけで分かりやすい
- 読み合いが明確
- ログ表示しやすい
- ランダム不要でもゲーム性が出る
- 後からスキルや状態異常を足しやすい

---

## 10. ログ表示例

### 初期ログ

- 対戦を開始しました。
- 行動を選択してください。

### 途中ログ

- 1ターン目: あなたは弱攻撃した。
- 1ターン目: 敵はカウンターした。
- 1ターン目: 敵のカウンターは失敗した。
- 1ターン目: 敵に4ダメージ。
- 2ターン目: あなたは強攻撃した。
- 2ターン目: 敵は弱攻撃した。
- 2ターン目: 敵に8ダメージ。
- 2ターン目: あなたは4ダメージを受けた。

### 決着ログ

- 6ターン目: 敵に7ダメージ。
- 敵を倒した。
- あなたの勝利。

---

## 11. まず切るべきもの

完成率を上げるため、以下は最初から削る判断でよいです。

- アニメーション
- 音
- 装備
- 複数敵
- 属性
- クリティカル
- 回避
- 通信対戦
- ガチャ
- ランキング
- マップ移動

---

## 12. 実装開始時の最初のTODO

優先順に並べると以下です。

1. Laravel + Inertia + Vue のプロジェクト起動
2. Tailwind の適用
3. migration 作成
4. preset seeder 作成
5. battle 生成処理
6. battle 表示画面
7. action 送信
8. turn 解決
9. 勝敗表示
10. 再戦

---

## 13. 次の一歩

この設計の次にやるべきことは、以下のどちらかです。

1. migration と model の雛形を作る
2. battle 画面の Vue モックを作る

おすすめは **先に画面モックを作ってから backend をつなぐ** です。

理由は、完成形のイメージが明確になり、途中で迷いにくくなるためです。
