# Validation Checklist

## 必須

1. 先に `cli` モードを試行した。
2. 期待結果を1文で定義した。
3. 実行コマンドと要点結果を対応付けた。
4. 判定を `PASS` / `FAIL` / `UNCONFIRMED` で明示した。
5. 管理画面確認時は `Accept-Language` ヘッダ付きで実行した。
6. 投稿テストでは保存後に `content` カラムをDB確認した（`content_len > 0`）。

## Browserモード追加条件

- CLIだけで判定不能
- UI崩れや視覚差分の確認が必要
- ユーザーが目視確認を明示要求
- サンドボックス制約で起動失敗した場合は、制限外実行へ切り替えて再試行した

## モード規約

- 既定: `cli`
- Browser任意: `playwright-headless`
- 目視確認: `playwright-headed`（明示指定時のみ）

## 投稿テスト補足

- RTE有効時は `textarea` 直接 `fill()` だけで保存されない場合がある。
- TinyMCE/CKEditor API同期（`triggerSave` / `updateElement`）を先に試す。
- 新規投稿で本文未反映なら編集画面（`a=27&id=...`）で再保存して再確認する。
