# Commit Message Guidelines

このドキュメントはコミットメッセージ生成の詳細規約を定義する。

## フォーマット

```txt
<type>(optional scope): <subject>
```

## 必須ルール

* Conventional Commits 準拠
* `type` は英語固定
* `subject` は日本語、簡潔、現在形、句点なし

## type 一覧

| type     | 用途 |
| -------- | ---- |
| feat     | 新機能 |
| fix      | 不具合修正 |
| refactor | 内部改善 |
| perf     | 性能改善 |
| docs     | ドキュメント |
| style    | 形式修正 |
| test     | テスト |
| chore    | 雑務 |
| ci       | CI変更 |

## 例

```txt
feat(parser): キャッシュ生成前にフックを追加
fix(db): 実行直前でエスケープ処理を統一
```
