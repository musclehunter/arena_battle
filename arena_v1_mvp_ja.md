# Arena v1 — MVP 実装まとめ

> 本ドキュメントは、既存の Laravel + Inertia + Vue バックエンドをベースに、
> 新設計スコープ (<ref_file file="/d:/masanobu/dev/ArenaBattle_0/arena_v1_ui_scope_ja.md" /> 等) に近い
> MVP フロントエンドとアセット生成パイプラインを構築したまとめです。

---

## 1. MVP の範囲

### 含まれるもの

- 画像アセット生成スクリプト (`backend/scripts/generate-images.js`)
- 画像アセットマニフェスト (`backend/scripts/assets-manifest.json`)
- キャラクターアイコンの動的参照・フォールバック (`resources/js/Components/CharacterIcon.vue`)
- ホーム画面、家門ダッシュボード、求職者市場、バトル画面の UI 刷新
- 既存 1v1 ターン制バトルへの新 UI 適用

### 含まれないもの（次フェーズ）

- 5v5 オートバトルへのバックエンド移行
- ATB ゲージのリアルタイム制御（現在はビジュアルのみ）
- 生産/市場/血盟/外交システムの本実装

---

## 2. 画像アセット生成パイプライン

### スクリプト

- `backend/scripts/generate-images.js`
- `backend/scripts/assets-manifest.json`
- `backend/scripts/README.md`

### 対応プロバイダー

| プロバイダー | 要APIキー | 備考 |
|---|---|---|
| Pollinations | 不要 | デフォルト。無料だがキューが混みやすい。 |
| OpenAI (DALL-E 3) | 要 | `OPENAI_API_KEY` |
| Stability AI | 要 | `STABILITY_API_KEY` |
| Placeholder | 不要 | SVGで即座にMVP確認可能。 |

### 実行例

```powershell
cd backend

# プレースホルダー（SVG）を即座に生成
npm run generate-assets

# 本物の画像を Pollinations で生成
npm run generate-assets:real
```

### 生成されるアセット

```
public/images/
├── characters/icons/400/   # {icon_key}_{gender}_{icon_index}_400.{png|svg}
├── backgrounds/             # title.png, arena.png, dashboard.png, dungeon.png
└── ui/                      # gold.png, hp.png, atk.png など
```

---

## 3. フロントエンド変更点

### 更新ファイル

| ファイル | 変更内容 |
|---|---|
| `resources/js/Components/CharacterIcon.vue` | 画像の PNG → SVG → インラインプレースホルダーの三段階フォールバック。 |
| `resources/js/Pages/Home/Guest.vue` | タイトル背景画像を使用したゲストランディング。 |
| `resources/js/Pages/House/Mine.vue` | 家門ダッシュボード。雇用キャラ一覧とクイックアクション。 |
| `resources/js/Pages/JobSeekers/Index.vue` | 求職者市場。キャラカードを刷新。 |
| `resources/js/Pages/Battle/Show.vue` | バトル画面。俯瞰風フィールド + 左右ステータスパネル。 |
| `resources/js/Components/Battle/BattleActionButtons.vue` | 行動ボタンを新設計の方針ラベル風に表示（内部は既存 weak/strong/counter）。 |
| `resources/js/Components/Battle/BattleStatusPanel.vue` | HPバー・ステータスの視認性向上。 |
| `resources/js/Components/Battle/BattleLogPanel.vue` | 折りたたみ可能なログパネル。 |
| `resources/js/Pages/Dashboard.vue` | 家門ダッシュボードへの導線を追加。 |

### バックエンド変更点

| ファイル | 変更内容 |
|---|---|
| `app/Http/Resources/BattleViewModel.php` | プレイヤー・敵の icon_key / icon_index / gender を追加。 |
| `database/seeders/CharacterPresetSeeder.php` | 全プリセットに icon_key を追加。 |
| `backend/package.json` | `generate-assets`, `generate-assets:real` スクリプトを追加。 |

---

## 4. 既知の制限

### バトルシステム

- 現在のバックエンドは **1v1 ターン制** (`weak` / `strong` / `counter`) です。
- UI 上では「攻撃的 / スキル優先 / 防御的」と表示していますが、内部的には既存アクションを送信しています。
- 5v5 オートバトルへの移行は、戦闘エンジンの再実装が必要です。

### アセット

- Pollinations はキューが混雑すると 500 を返すことがあります。その場合、該当ファイルは SVG プレースホルダーにフォールバックします。
- `icon_index` 0〜2 までの画像を生成済み。3〜8 はフォールバックプレースホルダーが表示されます。

### 環境

- ホストに PHP/Docker がないため、バックエンドサーバーは起動確認していません。
- `npm run build` は成功しています。

---

## 5. 起動手順

```powershell
# 1. Docker コンテナ起動
docker compose up -d

# 2. 依存関係 & マイグレーション
docker compose exec app composer install
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate:fresh --seed

# 3. フロントエンド
cd backend
npm run generate-assets   # または npm run generate-assets:real
npm run build

# 4. ローカル確認
# http://localhost
```

---

## 6. 画像を差し替える

1. `backend/scripts/assets-manifest.json` のプロンプトを調整。
2. 不要な画像を削除 or `--placeholder` で上書き。
3. `npm run generate-assets:real` を再実行。
4. `npm run build` で反映。

---

## 7. 次のステップ

- 5v5 オートバトルのバックエンド再設計
- 生産 / 市場 / 血盟 / 外交 UI の実装
- 画像の品質調整（プロンプト精緻化、プロバイダー切り替え）
- 効果音・演出の追加
