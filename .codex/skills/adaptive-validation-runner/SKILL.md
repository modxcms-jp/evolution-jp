---
name: adaptive-validation-runner
description: Web画面の動作確認をCLI優先で実施し、CLIで判定不能な場合のみPlaywrightによるE2E/ブラウザ操作へ切り替える検証スキル。ユーザーが「重さを抑えて確認したい」「まずCLIで確認したい」「ブラウザ操作でしか確認できない内容を検証したい」「目視で最終確認したい」と要求する場合に使う。
---

# Adaptive Validation Runner

既定はCLI検証。ブラウザは必要時のみ使う。

## モード選択

1. `cli`（既定）: `curl` / `php evo` / ログ確認だけで検証する。
2. `playwright-headless`（任意）: ブラウザUIを表示せず、DOM検証とスクリーンショットを取る。
3. `playwright-headed`（任意）: 目視確認が必要な場合だけブラウザを表示する。

`playwright-headed` の明示指定がない限り、ブラウザUIは起動しない。

## Workflow

### 1. 事前確認

1. 対象URL、期待結果、確認対象（文言/状態/遷移）を固定する。
2. 認証が必要な場合はCLIで可能な範囲（ステータス、レスポンス、ログ）を先に確認する。
3. 管理画面（`/manager/`）検証では `HTTP_ACCEPT_LANGUAGE` を必ず送る（未指定だと404になる実装がある）。

### 2. CLI検証（既定）

1. `curl -I` でHTTPステータスとリダイレクトを確認する。
2. `curl -s` で本文断片（期待文言）を確認する。
3. 必要に応じて `php evo`（`config:show`, `db:query`, `log:show`）で裏付けを取る。
4. CLI根拠で判定できる場合はここで完了する。

管理画面URLの例:
- `curl -I -H 'Accept-Language: ja,en;q=0.9' http://localhost/manager/`
- `curl -s -H 'Accept-Language: ja,en;q=0.9' http://localhost/manager/`

### 3. Browser検証（任意）

1. CLIだけで判定できない場合に限り Playwright を使う。
2. 既定は `playwright-headless`。視覚確認が必要な場合だけ `playwright-headed`。
3. 取得物（スクリーンショット、観測DOM、操作手順）を記録する。
4. ブラウザ起動がサンドボックスで失敗した場合は、制限外実行へ切り替えて再実行する。

よくある失敗シグナル:
- `sandbox_host_linux.cc`
- `Operation not permitted`
- `Target page, context or browser has been closed`

上記は「毎回必ず発生」ではない。環境や実行権限に依存して発生するため、失敗時のみ制限外実行へ切り替える。

### 4. 投稿テスト（RTEあり）

1. 新規投稿は `a=4`、作成後の編集は `a=27&id=<id>` を使う。
2. 本文は次の順で設定する。
   - TinyMCE: `setContent()` + `triggerSave()`
   - CKEditor: `setData()` + `updateElement()`
   - フォールバック: `textarea#ta` または `textarea[name=\"ta\"]` へ直接代入
3. 保存後は画面遷移だけで判定せず、DBで `content` の長さと先頭文字列を確認する。
4. `a=4` で本文反映しない場合は `a=27&id=<id>` で再保存して再確認する。

## 返答テンプレート

```text
モード: cli | playwright-headless | playwright-headed
対象: <URL/画面>
期待: <期待結果>

実行ログ:
- command: <実行コマンド>
  result: <要点>

観測:
- <事実1>
- <事実2>

判定: PASS | FAIL | UNCONFIRMED
不足情報/次アクション:
- ...
```

## References

- 判定粒度と記録項目は `references/checklist.md` に従う。
