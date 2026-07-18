# ArenaBattle_0 画像アセット生成スクリプト

`generate-images.js` は、AI画像生成APIまたはSVGプレースホルダーを使って、ゲームに必要な画像アセットを自動生成・配置するスクリプトです。

## クイックスタート

```powershell
cd D:\masanobu\dev\ArenaBattle_0\backend

# 1. 無料の Pollinations API で本物の画像を生成
node scripts/generate-images.js --provider=pollinations --category=all

# 2. または、まずプレースホルダー（SVG）だけ生成して動作確認
node scripts/generate-images.js --placeholder --category=all
```

生成された画像は `public/images/...` に保存され、Laravel の `public/` 配下なので `/images/...` として直接アクセスできます。

## 設定

### 環境変数

| 名前 | 説明 |
|---|---|
| `IMAGE_PROVIDER` | デフォルトのプロバイダー |
| `OPENAI_API_KEY` | `openai` 使用時に必要 |
| `STABILITY_API_KEY` | `stability` 使用時に必要 |
| `POLLINATIONS_PARAMS` | Pollinations 追加パラメータ（例: `nologo=true`） |

### マニフェスト

`assets-manifest.json` に生成対象、プロンプト、出力パス、命名規則を定義しています。

```json
{
  "categories": {
    "characters": { ... },
    "backgrounds": { ... },
    "ui": { ... }
  }
}
```

- `characters`: キャラクターアイコン（`public/images/characters/icons/400/{icon_key}_{gender}_{icon_index}_400.png`）
- `backgrounds`: 背景画像（`public/images/backgrounds/{name}.png`）
- `ui`: UIアイコン（`public/images/ui/{name}.png`）

## プロバイダー

### Pollinations（推奨・無料）

APIキー不要。1画像あたり少し時間がかかる場合があります。

```powershell
node scripts/generate-images.js --provider=pollinations --category=characters --limit=3
```

### OpenAI (DALL-E 3)

```powershell
$env:OPENAI_API_KEY="sk-..."
node scripts/generate-images.js --provider=openai --category=characters
```

### Stability AI

```powershell
$env:STABILITY_API_KEY="sk-..."
node scripts/generate-images.js --provider=stability --category=characters
```

### Placeholder（SVG）

画像生成APIを使わず、色付きのSVGプレースホルダーを生成します。MVPの骨組み確認や開発中に便利です。

```powershell
node scripts/generate-images.js --placeholder
```

## オプション

| オプション | 説明 |
|---|---|
| `--provider=NAME` | プロバイダー選択 |
| `--category=NAME` | カテゴリ選択（`characters` / `backgrounds` / `ui` / `all`） |
| `--limit=N` | 先頭N件のみ生成（テスト用） |
| `--dry-run` | 実際にはダウンロードせず、URL/パスを表示 |
| `--placeholder` | SVGプレースホルダー生成 |

## フロントエンドへの組み込み

`CharacterIcon.vue` は以下の順で画像を解決します。

1. `VITE_ASSET_BASE_URL` が設定されていればそこを参照（本番R2）
2. 未設定または画像が見つからなければ `/images/characters/icons/400/...` を参照
3. それでもなければSVGプレースホルダー（イニシャル＋グラデーション）を表示

ローカル開発時は `.env` の `VITE_ASSET_BASE_URL` を空にするか、`public/images` 配下に画像を置いてください。

## 自動化

CI/CD やセットアップ時に自動実行する場合:

```json
// package.json scripts に追加
"generate-assets": "node scripts/generate-images.js --placeholder",
"generate-assets:real": "node scripts/generate-images.js --provider=pollinations"
```

```powershell
# 初回セットアップ時に画像を生成
npm run generate-assets
npm run build
```
