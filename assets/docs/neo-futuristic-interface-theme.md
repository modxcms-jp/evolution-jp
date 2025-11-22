# Neo-Futuristic Interface テーマ生成プロンプト

## ねらい
管理画面向けのライトトーンを基調としつつ、軽い透明レイヤーや幾何学的なパネル構造で未来感を出すプロンプトです。左ペインは明るい背景を維持し、ゲームUI風や過度な発光を避けます。ネオンは極細線のみ、形状と情報設計で未来感をつくります。

## デザイン上の制約
- 左ペインはライト背景のまま維持する。ダーク化や黒背景は禁止。
- ネオンカラーは極細のアクセントラインに限定し、全面的な発光表現は禁止。
- 未来感は形状と情報構造で示し、過度な光沢・グローを避ける。
- 微細な層構造や半透明レイヤーを重ねて立体感を控えめに表現する。
- ゲームUI風の装飾や重量感のあるメカニカル表現は避ける。

## ビジュアル言語
- ベースカラー: 白〜淡いグレーの明るい背景。
- 形状: わずかにカットした角や緩やかなエッジで未来的なパネル感を付与。
- アクセント: シアン/マゼンタ系の極細ラインを必要最小限に使用。
- テクスチャ: 薄い幾何学的ラインパターンで領域境界を示す。
- レイヤー: 軽い透過のパネルやカードを重ね、情報階層を示す。

## レイアウトのポイント
- 左ペインは明るさを保ち、主パネルとのコントラストを大きくしない。
- コンテンツセクションはグリッド/カードで整理し、余白を広く取る。
- 情報の浮遊感を出すため、シャドウは微弱でソフトに抑える。
- 見出しとサマリーは半透明のトップバー、詳細は軽いカードで分節化。
- モジュール境界には極細のシアン/マゼンタ線か薄い幾何学パターンのみ。

### 構造化プロンプトのテンプレート
左ペインの明るさと非ゲーム的なトーンを固定し、各セクションの役割を明示したまま生成モデルに渡せるテンプレートです。不要な項目は省略してください。

```
[Context] Light admin UI dashboard. Keep left navigation bright white/light-gray; forbid dark mode and heavy glow.
[Layout] Grid of lightly transparent cards, wide spacing, soft micro-shadows. Top summary bar is semi-transparent; detail panels are light cards.
[Shapes] Gently cut corners or chamfered edges; thin geometric divider lines.
[Color] Base is bright white to pale gray. Accent only with ultra-thin cyan/magenta lines; no glowing fills.
[Components]
- Navigation: flat light background, hover with faint 2-4px outline only.
- Cards/Tables: subtle translucency (2-6%), thin grid lines, soft inner hover outlines.
- Forms: flat inputs with 1px neon line only on focus.
[Mood] Minimal, airy, no game UI look.
```

## コンポーネントのデザインメモ
- **ナビゲーション**: 左ペインは白〜淡グレーのフラット背景。ホバー時は 4px 以内の淡いアウトラインを付与し、背景色の大幅変更は行わない。
- **カード/パネル**: 2〜6% 程度の透過をかけた明度差で階層化。角は 4〜8px のカットコーナーまたは緩やかな面取り。
- **テーブル/リスト**: 行間に極薄のグリッドラインを使用。行ホバーはソフトな内側シャドウまたは淡い枠線で表現。
- **フォーム**: インプットはフラットな透明度と細いアウトライン。フォーカス時にだけ細いネオンラインを 1px で表示。
- **ステータス/タグ**: 彩度を抑えたペールトーンを基調に、重要度の高い要素のみ細いネオンサイン風ラインをアクセントとして付与。

## 避けるべき表現
- 左ペインや背景を暗転させるダークテーマ化。
- 面積の広いグロー、強いネオンの塗りつぶし、ゲームUI風の重量感。
- 厚みのあるメタリック質感や過度な立体表現。

## 生成用プロンプト例
```
管理画面UIのデザイン。白〜淡いグレーを基調に、未来感のある半透明レイヤーを重ねたテーマ。背景は明るくフラットで、要素の境界に薄い幾何学的ラインパターンを使用。角はわずかにカットされた“未来的な形状”。左ペインはライト背景のまま暗転させない。アクセントにはごく薄いネオンライン（シアンやマゼンタ）を細く入れるが、全体を暗くしない。パネルは軽い透過と微細な層構造で整理された印象を与える。ゲームUI風の重厚さや全面発光は避け、情報が浮かび上がるようなミニマルな未来感を演出する。
```

### 追加の文脈を指定する場合のバリエーション
```
・コンテンツ密度が高い分析ダッシュボードでも余白を確保し、左ペインは薄いグレーのまま固定する。
・KPIカードは半透明のトップレイヤーに数値を浮かせ、下層に薄い幾何学ラインを敷く。
・通知/警告は彩度を抑えたペールトーンのタグにし、発光やダーク化は行わない。
```

### ショートプロンプト（生成モデルに直接投げられる要約版）
```
Light admin UI, bright white-to-light-gray base. Left nav stays light, no dark mode. Geometric panels with lightly cut corners, soft micro-shadows, thin cyan/magenta accent lines only. Semi-transparent layers and subtle line grids for hierarchy; avoid glow and game-like visuals; keep everything minimal and airy.
```
