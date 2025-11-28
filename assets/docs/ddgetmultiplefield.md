# ddGetMultipleField スニペット

`ddGetMultipleField` は `mm_ddMultipleFields` ウィジェットが保存する複合データを展開し、テンプレートを通じてレンダリングするためのスニペットです。DivanDesign 版と同じパラメータ構成を持ち、既存の呼び出しをそのまま移植できます。

## 主な機能

- **入力ソースの柔軟性**: TV (`&tv`) から値を取得するほか、`&inputString` で直接文字列を渡すことも可能。
- **行・列のパース**: デフォルトで行区切り `||`、列区切り `::` を使用し、`&columns` で任意のプレースホルダー名を指定可能（未指定時は `col1`, `col2`, ... を自動生成）。
- **フィルタリングと並び替え**: `&where` で JSON 形式の等価フィルターを指定し、`&sortBy` / `&sortDir` で並び替えが可能。
- **表示制御**: `&offset` や `&display` でスキップ・上限を設定。`&display=all`（大文字小文字を区別しない）で全件表示。
- **テンプレート適用**: 行テンプレート `&rowTpl`（または別名 `&tpl`）で各行を描画し、`&rowSeparator` で結合。さらに `&outerTpl` で全体をラップできます。
- **プレースホルダー出力**: `&toPlaceholder` で出力を任意のプレースホルダーに保存。`&idxPlaceholder` により 1 始まりの行番号プレースホルダー名を変更可能。
- **レガシー互換性**: `&owner` / `&ownerId` / `&api` など旧仕様のプレースホルダーも維持し、`&owner` が指定された場合はラップ時にこれらを渡します。

## 典型的な呼び出し例

```html
[[ddGetMultipleField?
    &tv=`gallery`
    &columns=`title,description,url`
    &rowTpl=`@CODE:<li><a href="[+url+]">[+title+]</a><br />[+description+]</li>`
    &outerTpl=`@CODE:<ul>[+result+]</ul>`
    &sortBy=`title`
    &sortDir=`asc`
]]
```

## パラメータ一覧

| パラメータ | 説明 | デフォルト |
| --- | --- | --- |
| `docId` | 値を取得するドキュメントID。 | 現在のドキュメント |
| `tv` | 複合データを保持する TV 名または ID。`inputString` 指定時は無視。 | — |
| `inputString` | `rowDelimiter` / `colSeparator` で区切られた生の複合データ文字列。 | — |
| `columns` | プレースホルダー名のカンマ区切りリスト。未指定時は `col1` などを自動生成。 | — |
| `rowDelimiter` | 行の区切り文字列。 | `||` |
| `colSeparator` | 行内の列の区切り文字列。 | `::` |
| `rowTpl` / `tpl` | 各行を描画するチャンク名または `@CODE:` 付きテンプレート文字列。 | — |
| `outerTpl` | 全体をラップするチャンク名または `@CODE:` 付きテンプレート文字列。`[+result+]` を受け取る。 | — |
| `rowSeparator` | 行と行の間に挿入する文字列。 | — |
| `offset` | 先頭からスキップする行数。 | `0` |
| `display` | 表示する最大行数。空または `all`（大文字小文字を区別しない）で全件表示。 | `all` |
| `sortBy` | 並び替えに使用する列キー。 | — |
| `sortDir` | 並び順。`asc` または `desc`。 | `asc` |
| `where` | 等価比較によるフィルター条件（JSON 形式）。 | — |
| `idxPlaceholder` | 行番号（1 始まり）のプレースホルダー名。 | `idx` |
| `toPlaceholder` | 出力先のプレースホルダー名。指定時は画面には出力しない。 | — |
| `owner`, `ownerId`, `api` | レガシー呼び出し向けの互換プレースホルダー。`owner` が指定された場合にラップ時へ渡す。 | — |

## 実装メモ

- テンプレート取得は `@CODE:` を優先し、チャンクが見つからない場合は空文字を返します。
- `where` が JSON として解釈できない場合はフィルターを無視し、入力データをそのまま処理します。
- `display` や `offset` の計算後、行配列に `idxPlaceholder` で指定した番号が追加されるため、テンプレート側で行番号を簡単に参照できます。
