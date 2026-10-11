# ExecPlan: 管理画面の入力の扱いと、状態を変える操作の CSRF 対策を土台から揃える

## Purpose / Big Picture

管理画面の処理（processor）がリクエストの値を受け取る書き方と、状態を変える操作を受け付ける条件を、決まった一つの方式に揃える。個々の箇所を見つけ次第直すのではなく、入口のヘルパーと判定を一か所にまとめ、新しく書くコードも自然に安全になり、CI で逸脱を検出できる状態にする。Issue #493 の対応。

## Progress

- [ ] (2026-10-10) M1: id を受け取る入口を `request_intvar()` に統一する
- [ ] (2026-10-10) M2: 状態を変えるアクションの一覧を定義し、GET でも CSRF トークンを必須にする
- [ ] (2026-10-10) M3: CI でリクエスト値の扱いと JS への埋め込みの逸脱を検出する
- [ ] (2026-10-10) M4: 検証スクリプトを整え、main と比べて確認する

## Surprises & Discoveries

- 2026-10-11（レビュー #506 で判明）: `manager/includes/accesscontrol.inc.php` の POST 向け CSRF 検証が、ログアウト（`a=8`）を「ログイン処理」として検証から除外している。ログアウトはセッションを破棄する更新操作であり、本来は保護対象。M2 で是正する。
- 2026-10-11（1.2.2J の作業で確認）: DBAPI は全体が「値は呼び出し側で安全化してから渡す」取り決めで動いている。`where()` に加えて、`db()->update()` / `insert()` に配列で渡した値も、`'%s'` で囲むだけでエスケープされない（`manager/includes/extenders/dbapi/mysqli.inc.php` の `update()`）。`select()` などの条件文字列も同じ。安全化の付け忘れは `where()` の呼び出しに限らず、配列の値・文字列の連結にもある。

## Decision Log

- 2026-10-10 / yamamoto・Claude: SQL の条件を組み立てる `where()` が値をエスケープしていなかった件は、先行して #505 で直した（`where()` の中でエスケープする）。この ExecPlan は、その土台の上で、入力の受け取り方と CSRF を揃える。
- 2026-10-10 / yamamoto・Claude: id の受け取りには新しいヘルパーを作らず、既存の `request_intvar()` を「整数を返す」ように改めて統一する。AGENTS.md の「局所的な新方式の導入は禁止。改善は横断的に適用する」に従う。代替案（`request_id()` の新設）は、既存の 47 か所の呼び出しと二系統になるため採らない。
- 2026-10-10 / yamamoto・Claude: CSRF は、状態を変えるアクションの一覧を一か所で定義し、メソッド（GET / POST）によらずトークンを必須にする。すべてを POST に変える案は、呼び出し元（リンク・ツリーのメニュー・ボタン）の作り替えが大きく、段階的に移行できないため、この ExecPlan では採らない。将来 POST に寄せる場合も、一覧はそのまま使える。
- 2026-10-10 / yamamoto・Claude: トークンは、シェル（`EvoShell`）の fetch では既存どおり `X-CSRF-Token` ヘッダーで、フルページの遷移（`location.href` への代入やリンク）では URL の `csrf_token` パラメータで受け取る。URL に載ったトークンはアクセスログや Referer に残り得るが、トークンはログアウトで破棄されるセッション単位の値であり、GET で状態が変わる今の状態よりは大きく改善するため許容する。
- 2026-10-10 / yamamoto・Claude: CI での検出は、DB に接続しない単独の PHP スクリプトにする。`php evo` の CLI は DB 接続を前提にしているため、DB のない CI では使えない。
- 2026-10-10 / yamamoto・Claude: このリポジトリは公開されているため、まだ直していない具体的な箇所の一覧はこの ExecPlan に書かない。M1・M2 の最初に、棚卸しのコマンドで手元に一覧を作って作業する。
- 2026-10-11 / Claude（レビュー反映 #506）: id の検証は受け取り元によらず同じにする。PHP の `(int)` は `"5abc"` を `5` にしてしまい「数字以外は 0」を満たせないため、`(int) postv()` は使わず、POST 限定でも厳密に検証する `post_intvar()` を用意する。
- 2026-10-11 / Claude（レビュー反映 #506）: 同じアクション番号で表示と更新を兼ねる処理（`a=31` の `mode=delete` など）があるため、CSRF の一覧はアクション番号だけでなくサブ操作も見る組で定義する。将来は更新を別アクションへ分けるのが望ましいが、この計画の範囲では番号＋サブ操作とする。
- 2026-10-11 / Claude（レビュー反映 #506）: CSRF 検証はメソッド（GET/POST/HEAD）によらず、一覧に該当する操作で行う。現状の `is_post()` 限定は GET・HEAD で迂回できる。あわせて、既存の POST 検証が `a=8`（ログアウト）を「ログイン処理」として除外しているのは誤り。除外するのは認証前でトークンを持てないログインだけとし、ログアウトは一覧に含める。
- 2026-10-11 / Claude（レビュー反映 #506 CodeRabbit）: 既存の「ログイン以外の POST は検証」は置き換えず残し、一覧の操作に GET・HEAD の検証を足す形にする。置き換えると、一覧に載せ漏れた状態変更アクションへの POST が検証から外れ、CSRF の守りが後退する。保険として従来の POST 検証を残すことで、一覧が不完全でも POST は守られる。
- 2026-10-11 / yamamoto・Claude: 上の 2026-10-10 の決定（#505 で `where()` の中でエスケープする）を改める。`where()` だけが値をエスケープすると、DBAPI の取り決め（呼び出し側で安全化してから渡す）と食い違い、先にエスケープしている外部プラグインでは二重エスケープになる。1.2.2J と同じく `where()` は元の仕様に戻し、安全化していなかった呼び出し側を `db()->escape()` / `(int)` で直した（main は `fix/where-callsite` の PR）。取り決めそのものを変える（値のバインドへ移る）のは、PDO 移行（#453）のフェーズ 2 で扱う。
- 2026-10-11 / Claude: その PR では、id を `(int)` で整数にしている。M1 で `request_intvar()` / `post_intvar()` に揃えるときに、これらも置き換え対象に含める（`(int)` は SQL としては安全だが、`"5abc"` を `5` として扱う）。

## Outcomes & Retrospective

## Context and Orientation

用語:
- **processor**: 管理画面で保存・削除・公開などの処理を行う PHP ファイル。`manager/processors/` 以下にあり、`manager/index.php` の `switch (manager()->action)` から、URL の `a`（アクション番号）に応じて読み込まれる。
- **アクション番号（`a`）**: 管理画面の URL の `index.php?a=61&id=5` の `61` のように、どの画面・処理を動かすかを示す番号。
- **CSRF**: ログイン中の利用者を、本人の意図しないリクエストへ誘導して操作させる攻撃。対策として、正規の画面だけが知っている値（CSRF トークン）をリクエストに付けさせ、サーバーで照合する。
- **シェル（EvoShell）**: 管理画面のメイン領域だけを fetch で差し替える仕組み（`manager/media/script/shell.js`）。

現状:
- リクエストの値は `getv()` / `postv()` / `anyv()`（`manager/includes/helpers.php`）で読む。id を数値として読むヘルパー `request_intvar($key)`（`helpers.php`）はあるが、正規表現で確かめた**文字列**を返し、使っている箇所と使っていない箇所が混在している。整数への変換の有無も、processor ごとにばらばら。
- `where()`（`manager/includes/extenders/ex_dbapi.php`）は値をエスケープしない。DBAPI 共通の取り決めとして、呼び出し側で `db()->escape()` または `(int)` してから渡す。`$field` と `$op` もそのまま埋め込まれる。`db()->update()` / `insert()` の配列の値も同じ（エスケープされない）。
- CSRF トークンの実装は `manager/includes/csrf_token.php`。トークンはセッション単位で、検証しても消えない（複数タブのため）。受け付ける場所は POST の `csrf_token` と `X-CSRF-Token` ヘッダーだけ（`getRequestCsrfToken()`）。
- 検証は `manager/includes/accesscontrol.inc.php` の `if (is_post()) { checkCsrfToken(); }` だけで、GET は検証していない。管理画面には、GET で状態が変わるアクションが多数ある。
- クライアント側は `manager/media/script/csrf.js` が、POST のフォームに hidden の `csrf_token` を補い、同一オリジンへの fetch / XHR で GET 以外のときだけ `X-CSRF-Token` ヘッダーを付ける。`shell.js` の `request()` は、POST のときだけヘッダーを付ける。トークンは `header.inc.php` が出す `<meta name="csrf-token">` から読む。
- 値を JS へ埋め込むときは `js_literal()`（`helpers.php`）、HTML は `hsc()` を使う（AGENTS.md の「出力のエスケープ」）。

## Plan of Work

M1（入力の受け取り）では、`request_intvar($key)` を「正の整数なら int、それ以外は 0」を返すように改め、**受け取り元（GET / POST / 両方）に関わらず、整数の検証はこの関数に通す**。POST だけで受け取りたい処理のために、同じ検証を POST 限定で行う `post_intvar($key)` も用意する（`(int) postv()` は使わない。PHP の `(int)` は `"5abc"` を `5` にしてしまい、「数字以外を含む id は 0」を満たせないため）。既存の 47 か所の呼び出しで、戻り値を文字列として比べている箇所がないか確かめてから変える。そのうえで、processor が id を `getv()` / `anyv()` / `postv()` で直接読み、整数に変換せずに使っている箇所を棚卸しし、この 2 つのヘルパーへ置き換える。カンマ区切りの複数 id を受け取る処理は、各要素を整数にしてから使う。権限の判定（`checkPermissions()`）と、その後の更新で、必ず同じ整数の値を使う。

M2（CSRF）では、`manager/includes/csrf_token.php` に「状態を変える操作の一覧」と、それを判定する関数を置く。一覧に含めるかどうかは「DB・ファイル・**セッション**を書き換えるか」で判断し、表示だけの画面は含めない。**同じアクション番号で表示と更新を兼ねる処理がある**ため、判定はアクション番号だけでなく、必要に応じてサブ操作（`a=31` の `mode=delete` など）も見る。将来的には更新処理を別アクションに分けるのが望ましいが、この計画の範囲では、番号＋サブ操作の組で一覧を定義する。`accesscontrol.inc.php` の検証は、**既存の「ログイン以外の POST は検証する」を残したうえで**、一覧に該当する操作には GET・HEAD などの検証を**足す**（置き換えではない。置き換えると、一覧に載せ漏れた状態変更アクションへの POST が、今まで効いていた検証からも外れてしまう。保険として従来の POST 検証を残す）。これで、一覧が万一不完全でも、POST の守りは後退しない。あわせて、現在の POST 検証が `a=8`（ログアウト。セッションを破棄する更新操作）を「ログイン処理」として検証から外しているのは誤りなので、除外するのはログイン（認証前でトークンを持てない）だけにし、ログアウトは検証対象に戻す（一覧にも含める）。`getRequestCsrfToken()` は、POST の値・ヘッダーに加えて GET の `csrf_token` を受け付ける。クライアントでは、`csrf.js` が同一オリジンへの fetch / XHR に、メソッドによらずヘッダーを付ける（`shell.js` の GET もこれで付く）。フルページの遷移のために、URL へトークンを付ける共通の関数を、PHP（例: `csrfUrl($url)`）と JS（例: `EvoCsrf.url(url)`）の両方に用意し、状態を変える操作へのリンク・`location.href` の組み立てをこれに置き換える。

M3（CI での検出）では、`.github/scripts/security-lint.php` を作り、次を検出したら終了コード 1 で終わるようにする。processor の中で、`getv()` / `anyv()` / `postv()` の戻り値を SQL の文字列へ直接埋め込んでいる書き方。リクエストの値を、`db()->escape()` / `(int)` / `request_intvar()` を通さずに `where()` / `and_where()` の値や、`db()->update()` / `insert()` の配列の値へ渡している書き方。`<script>` の文脈で `addslashes()` や `db()->escape()` を使っている書き方。`manager/index.php` で processor を読み込むアクションのうち、状態を変えるのに一覧へ登録されていないもの（判定できないものは、一覧か除外リストのどちらかへの明示を求める）。既存の違反は、M1・M2 の完了時点で 0 件にする。GitHub Actions のワークフロー（`.github/workflows/security-lint.yml`）から PR ごとに実行する。

M4（検証）では、`.claude/skills/adaptive-validation-runner` の検証環境で、M1〜M3 の確認スクリプトを main と比べて流す。

## Concrete Steps

M1:
- 編集対象: `manager/includes/helpers.php`（`request_intvar()` を int 返却に改め、`post_intvar()` を追加）
- 実行: `grep -rn "request_intvar(" --include=*.php manager assets`
- 期待: 戻り値を `=== ''` や文字列として比べている箇所がない（あれば int に合わせて直す）
- 実行（棚卸し）: `grep -rnE "= (getv|anyv|postv)\\('(id|parent|new_parent|pid|docid)'" manager/processors manager/actions`（作成時点で約 40 件）
- 期待: 結果を手元の一覧にし、整数へ変換していないものを `request_intvar()` / `post_intvar()` へ置き換える。置き換え後、`checkPermissions()` とその後の更新が同じ整数の値を使っている
- 実行（確認）: `php -r 'require "manager/includes/helpers.php"; var_dump(request_intvar("x"));'` 相当で、`"5abc"` や `"0"`・空文字が 0 に、`"5"` が 5 になる（ヘルパー単体のユニット確認）

M2:
- 編集対象: `manager/includes/csrf_token.php`（アクション一覧・判定関数・`getRequestCsrfToken()`）、`manager/includes/accesscontrol.inc.php`、`manager/media/script/csrf.js`、状態を変えるアクションを呼ぶ画面・JS
- 実行（棚卸し）: `grep -rnE "a=[0-9]+" manager/actions manager/frames manager/media/script manager/media/style --include=*.php --include=*.js --include=*.tpl`
- 期待: 一覧に含めたアクションを呼ぶ箇所が、すべて `csrfUrl()` / `EvoCsrf.url()` か、シェル経由（ヘッダー付き）になっている

M3:
- 編集対象: `.github/scripts/security-lint.php`、`.github/workflows/security-lint.yml`
- 実行: `php .github/scripts/security-lint.php`
- 期待: M1・M2 の完了後に `0 violations` と出て、終了コード 0

M4:
- 編集対象（新規）: 作業用ディレクトリに `csrf-check.js`（`adaptive-validation-runner` の `lib.js` を読み込む確認スクリプト。リポジトリには含めない）
- 実行: `references/environment.md` の手順で検証環境を立て、`setup-site.js --sample --editor` と `smoke.js` を流す
- 実行: `NODE_PATH=<work>/node_modules node csrf-check.js fix <skill-scripts-dir>`
- `csrf-check.js` が確かめる各ケースと期待（DB の値とレスポンスで判定する。記号・不正値は無害な文字列のみ使う）:
  - 管理者がトークン付き（シェルのヘッダー経由、または `csrfUrl()` のリンク）で非公開化 `a=62&id=<n>` → `modx_site_content.published` が 0 になる
  - トークンなしで `index.php?a=62&id=<n>` を直接 GET → レスポンスが 403、`published` が変わらない
  - 同じく HEAD で呼ぶ → 403、`published` が変わらない
  - トークンなしでログアウト `a=8` を GET → 403（セッションが破棄されない）
  - `a=27&id=5abc`（テンプレート更新などの id に数字以外を混ぜる）を送る → id=0 として扱われ、`modx_manager_log` に SQL エラーが残らない
  - ファイル操作 `a=31&mode=newfolder`（サブ操作つき）をトークンなしで呼ぶ → 403、フォルダが作られない
- 期待: スモークテストと `csrf-check.js` が全 PASS。同じスクリプトを main で流すと、トークンなしの各ケースが「操作が通る／500」で FAIL する（修正前後の差が出る）

## Validation and Acceptance

- ログイン中の管理者が、管理画面のリンク・ツリーのメニュー・ボタンから、公開・非公開・削除・複製などの操作をすると、これまでどおり成功する（シェル経由と、フルページの遷移の両方）。
- 同じ操作の URL を、トークンを付けずに直接開くと（GET でも HEAD でも）、操作は行われず、403 になる（DB の値が変わらないことで確かめる）。サブ操作で更新する画面（`a=31&mode=newfolder` など）も同じ。
- ログアウト（`a=8`）も、トークンがなければ行われない（セッションが破棄されない）。
- トークンを付けた URL を、ログアウト後のセッションや別の利用者のセッションで開いても、操作は行われない。
- id に数字以外を含めて送ると（`5abc` など）、処理は id=0 として扱われ、別の既存レコードが更新されない。SQL のエラーもログに出ない。
- `php .github/scripts/security-lint.php` が 0 件で終わり、PR ごとに GitHub Actions で同じチェックが走る。違反を 1 つ足した試しのコミットでは、チェックが失敗する。
- 検証スキルのスモークテストが全 PASS する。

## Idempotence and Recovery

各マイルストーンは独立した PR にでき、途中で止めても既存の動作は壊れない順に進める。M2 は、まずトークンを「受け付ける」変更（ヘッダーと URL のトークンを読む・クライアントが付ける）を入れてから、最後に「必須にする」変更を入れる。必須化で問題が出た場合は、アクション一覧から外すことで、そのアクションの **GET・HEAD の検証だけ**を止められる（既存の POST 検証は残っているので、POST の守りは変わらない）。棚卸しのコマンドは何度実行しても結果は変わらず、置き換えの進み具合の確認に使える。

## Artifacts and Notes

- Issue: #493
- 先行して直したもの: #490（ドキュメントグループの権限判定）、#491（ブロック中のログイン）、#492・#504（JS への埋め込み）、#505（`where()` のエスケープ。のちに、取り決めに沿って呼び出し側を直す形へ改めた）
- 想定するコミットの単位: M1 は `request_intvar()` の変更と processor の置き換えを、ディレクトリ（document / pm / db / element など）ごとに分ける。M2 は「トークンを受け付ける」「呼び出し元に付ける」「必須にする」の 3 つに分ける。M3 はスクリプトとワークフローで 1 つ。
- 関連する計画: `.agent/plans/2026-07-08-pdo-migration-dbapi-refactor.md`（PR #453。未マージ）

## Interfaces and Dependencies

- PDO 移行（#453）のフェーズ 2（プレースホルダ化）とは、SQL への値の渡し方で重なる。この ExecPlan では `where()` とエスケープの仕組みを変えないので、フェーズ 2 と衝突しない。フェーズ 2 で値のバインドに移るときも、M1 で揃えた「整数の id」はそのまま使える。
  - フェーズ 2 の設計対象には、値のバインドだけでなく、`where()` のフィールド名（`$field`）と演算子（`$op`）の検証も含めること。これらは値と違ってプレースホルダにできず、現状はそのまま SQL に埋め込まれるため、許可リスト方式などで安全を担保する（1.2.2J の調査と、セカンドオピニオンでの指摘を反映）。PDO プラン本体（#453）にも申し送り済み。
- 外部のプラグイン・モジュールが、状態を変える管理画面のアクションを GET で直接呼んでいる場合、M2 の必須化のあとはトークンが必要になる。リリースノートの「アップグレード時の注意」に、`csrfUrl()` / `EvoCsrf.url()` を使うよう書く。
- 検証には `.claude/skills/adaptive-validation-runner` を使う。
