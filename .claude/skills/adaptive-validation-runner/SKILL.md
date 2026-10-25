---
name: adaptive-validation-runner
description: 改修後の動作確認を、AIが自分で環境を立てて最後まで進めるための検証スキル。CLI（curl / php evo / DB）を優先し、画面操作でしか判定できないときだけ Playwright（既定はヘッドレス）に切り替える。Docker の検証環境の起動、インストール、テスト用ユーザー作成、mailpit でのメール確認、main との比較、後片付けまでを扱う。「動作確認」「自走で確認」「実環境で確かめる」「ブラウザで確認」「再現を確認」「Playwright」と依頼されたとき、または修正のあとに実機での裏付けが必要なときに使う。
---

# Adaptive Validation Runner

本題の確認は CLI を既定とし、ブラウザは CLI で判定できないときだけ使う。
ただし検証環境の準備（`scripts/setup-site.js`）とスモークテスト（`scripts/smoke.js`）は、ヘッドレス Chromium を使う。
検証環境が無ければ、このスキルの手順と同梱スクリプトで立ち上げ、確認が終わったら片付ける。

## モード

1. `cli`（既定）: `curl` / `php evo` / DB 問い合わせ / ログで確認する
2. `playwright-headless`（任意）: 画面を表示せずに操作し、DOM・応答・スクリーンショットで判定する
3. `playwright-headed`（任意）: 目視が必要で、ユーザーが明示したときだけ画面を表示する

`playwright-headed` の明示がない限り、ブラウザの画面は表示しない。

## コマンド

### `/verify-live <確認したいこと>`

次の Workflow を順に実行し、最後に「返答テンプレート」で報告する。

## Workflow

### 1. 事前確認

1. 確認対象（URL・画面・操作）と期待結果を 1 文ずつで固定する
2. 修正前の状態でも確かめるか決める。不具合修正なら、原則として main で再現 → 修正ブランチで解消、の両方を確かめる
3. 確認に必要なデータ・ユーザー・設定（権限の弱いユーザー、メール送信方法、タイムゾーンなど）を洗い出す

### 2. 検証環境の準備（必要なときだけ）

既存の環境があればそれを使う。無ければ `references/environment.md` の手順で立ち上げる。要点:

1. `compose.yml` に `scripts/compose.validation.yml` を重ね、プロジェクト名 `${EVO_VALIDATE_NAME:-evo-validate}` で起動する（DB は mariadb:11、DB・SMTP のポートは外に出さない）
   - 起動の前に、同じ名前の環境がほかの作業ツリーで動いていないか確かめる。並走中のセッションがあり得るときは、`EVO_VALIDATE_NAME` と `EVO_BIND_IP`（127.0.0.2 など）を変える（`references/environment.md` の「並走するとき」）。ほかのセッションの環境は止めない・消さない
2. Playwright は作業用ディレクトリへ入れ、`NODE_PATH` で解決させる（リポジトリに `node_modules` を作らない）
3. `scripts/setup-site.js` でインストールからシステム設定の保存まで済ませる（`--sample` でサンプルサイト、`--editor` で権限の弱いユーザー）
4. `scripts/smoke.js` が全 PASS することを確かめてから、本題の確認に入る
5. 1.2 系（保守ブランチ・リリースタグ）で確かめるときは、`references/environment.md` の「1.2 系（保守ブランチ・リリースタグ）で検証するとき」に従い、`scripts/smoke.js` の代わりに `scripts/smoke-1.2.js` を使う

### 3. CLI での確認（既定）

1. `curl -I` でステータスとリダイレクト、`curl -s` で本文の断片を確かめる。管理画面には必ず `Accept-Language` を付ける（無いと 404）
2. 必要に応じて `php evo`（`config:show` / `db:query` / `log:tail` / `health:check` など）や DB で裏付けを取る
3. CLI の根拠で判定できるなら、ここで終える

### 4. ブラウザでの確認（任意）

1. CLI で判定できないとき（画面の構造、JS の動作、ダイアログ、遷移、見た目）だけ Playwright を使う
2. 確認スクリプトは作業用ディレクトリに置き、`scripts/lib.js` のヘルパーを使う。書き方は `scripts/smoke.js` を見本にする
3. 判定は PASS / FAIL で出力し、スクリーンショットと観測値（URL・応答ヘッダー・DOM の数など）を残す
4. 画面操作のつまずきどころは `references/environment.md` の「既知の落とし穴」を先に読む
5. 排他や取り合い（2 つの処理が同じジョブやロックを奪い合う動き）は、外からロックを握る・処理を遅らせるなどの人工的な隙間を作らずに、処理を遅くした実際の条件（`cache:clear` で書き出しを遅くするなど）で確かめる。隙間を作った確認だけで PASS にしない（相手がすぐロックを取り直して、片方がいつまでも通らない飢えを見落とす）

### 5. 修正前後の比較

1. 同じスクリプトを main（修正前）と対象ブランチ（修正後）で流す
2. ブランチを切り替えたら数秒待つ（OPcache が古いコードを返すことがある）
3. 結果は「条件 × main × 対象ブランチ」の表にまとめ、PR 本文の確認方法へ転記する

### 6. 後片付け

`references/environment.md` の「後片付け」に従い、検証環境・テスト用データ・作業ツリーに残ったファイルを片付ける。
ユーザーが目視で確かめたいと言った場合は、環境を残し、ログイン情報と URL を伝える。

## 投稿テスト（RTE あり）

1. 新規投稿は `a=4`、作成後の編集は `a=27&id=<id>` を使う
2. RTE が有効なときは `textarea` へ直接 `fill()` するだけでは保存されない場合があるため、本文は次の順で設定する
   - TinyMCE: `setContent()` + `triggerSave()`
   - CKEditor: `setData()` + `updateElement()`
   - フォールバック: `textarea#ta` または `textarea[name="ta"]` へ直接代入
3. 保存後は画面遷移だけで判定せず、DB で `content` の長さと先頭文字列を確かめる
4. `a=4` で本文が反映されない場合は、`a=27&id=<id>` で再保存して確かめ直す

## 返答テンプレート

```text
モード: cli | playwright-headless | playwright-headed
環境: <既存 / <EVO_VALIDATE_NAME> を起動（アドレス）>（ブランチ: <名前>）
対象: <URL/画面>
期待: <期待結果>

結果:
| 条件 | main | 対象ブランチ |
| --- | --- | --- |
| ... | ... | ... |

観測:
- <事実1>
- <事実2>

判定: PASS | FAIL | UNCONFIRMED
未確認・次アクション:
- ...
```

## References

- 検証環境の起動・設定・後片付けと既知の落とし穴: `references/environment.md`
- 古い版の DB を作り、アップグレード後のスキーマを今の新規インストールと比べる道具（`scripts/upgrade-fixture.js`）: `references/upgrade-fixtures.md`
- 判定の粒度と記録項目: `references/checklist.md`
- 同梱スクリプト: `scripts/compose.validation.yml`（compose の上書き）/ `scripts/lib.js`（共通ヘルパー）/ `scripts/setup-site.js`（インストールと初期設定）/ `scripts/smoke.js`（スモークテスト兼見本）/ `scripts/smoke-1.2.js`（1.2 系向けのスモークテスト）
