# ExecPlan: リクエスト値を型ごとに取り出すヘルパーを用意し、空の値の扱いを GET と POST でそろえる

## Purpose / Big Picture

管理画面・同梱のスニペットとプラグイン・インストーラが受け取るリクエスト値（URL のクエリやフォームの送信値）を、型が保証された形で取り出すようにする。これで、パラメータを欠いた・空の・配列を送ったリクエストで、PHP 8.1〜8.5 の非推奨の警告や TypeError、想定外の SQL が出なくなる。あわせて、GET と POST で空の値の扱いが違う状態をなくし、`getv()` を `postv()` に書き換えても動きが変わらないようにする。

Issue: #1059

## Progress

- [ ] (2026-11-05) M1: 型つきヘルパーの追加と、決まりの文書
- [ ] M2: `getv()` の空の値の特例をやめ、特例に頼る呼び出し元を型つきへ置き換える
- [ ] M3: `manager/actions/` の置き換え
- [ ] M4: `manager/processors/` の置き換え
- [ ] M5: `manager/includes/`・`manager/media/`・`manager/frames/` ほか `manager/` 直下の置き換え
- [ ] M6: `assets/`（スニペット・プラグイン・モジュール）と `install/` の置き換え
- [ ] M7: 置き換え漏れを検出する検査を `security-lint.php` に足す
- [ ] M8: PHP 8.5 と PHP 7.4 での横断の確認と振り返り

## Surprises & Discoveries

- 2026-11-05: 型つきの取り出し口は、ID 専用の `get_intvar()` / `post_intvar()` / `request_intvar()` / `to_intvar()` / `abort_if_invalid_id()` として既にある（`manager/includes/helpers.php`、呼び出し元は約 150 か所）。新しい仕組みは作らず、この一族を広げる。
- 2026-11-05: #1058 で、`getv()` の引数なし呼び出しによる null 添字、リクエスト値の既定値 `null` を `preg_match()` などへ渡す約 20 か所、モジュールの依存関係の削除で `count(null)` が TypeError になる不具合を直した。いずれもこの計画で防ぎたい形の実例。

## Decision Log

- 2026-11-05 / yamamoto・Claude: 取り出し口の形は、既存の `*_intvar()` を広げる（受け取り元の接頭辞 `get_` / `post_` / `request_` × 型）。代替案の `request()->query()->int('id')` のような 1 つの入口は Symfony に近いが、既存の約 150 か所も置き換えになり、今の作法から外れるため採らない。
- 2026-11-05 / yamamoto・Claude: 空の値は「生の値はそのまま、型つきは既定値」にする。`getv()` / `postv()` / `anyv()` はキーがあれば空文字をそのまま返し、既定値はキーが無いときだけ使う（Symfony の InputBag と同じ）。型つきの取り出し口は、空・形式違い・配列などの想定外の型を、すべて既定値にする。代替案の「すべて空なら既定値」は、保存で項目を空にしたい場合に既定値が入る恐れがあり、`postv()` の約 540 か所の確認が重いため採らない。「型つきも空はそのまま」は、型つきで空を受けたときの値が型ごとにばらつくため採らない。

## Outcomes & Retrospective

## Context and Orientation

用語:

- リクエスト値: ブラウザが送ってくる値。URL の `?id=5` のようなクエリ（GET）と、フォームの送信内容（POST）がある。PHP では `$_GET` / `$_POST` / `$_REQUEST` に入るが、このプロジェクトでは直接触らず、ヘルパーを通す（AGENTS.md の「スーパーグローバル変数の禁止」）。
- 生の値のヘルパー: `getv($key, $default)`（GET）、`postv()`（POST）、`anyv()`（GET と POST の両方）。値を変換せずに返す。キーを省略すると全件を配列で返す。中身は `array_get()`。
- 型つきのヘルパー: 決まった型の値だけを返し、それ以外は既定値を返すヘルパー。今は ID 用の `*_intvar()` だけがある。

関係するファイル:

- `manager/includes/helpers.php`: `array_get()`（373 行付近、4 つ目の引数 `$validate` で値を確かめられる）、`*_intvar()` と `to_intvar()`（425〜470 行付近）、`getv()` / `postv()` / `anyv()`（503〜551 行付近）。
- `.github/scripts/security-lint.php`: processor の中でリクエスト値を無害化せずに SQL へ入れる書き方などを検出する。CI（`.github/workflows/security-lint.yml`）で動く。
- 文書: ヘルパーの決まりは今は AGENTS.md の「スーパーグローバル変数の禁止」にしか無い。

今の状態（2026-11-05、grep による数）:

- 呼び出し元は `getv` 238・`postv` 544・`anyv` 235 か所。ディレクトリごとでは `manager/actions/` 360、`manager/processors/` 363、`manager/includes/` 66、`manager/media/` 32、`manager/frames/` 14、`assets/snippets/` 37、`assets/plugins/` 46、`assets/modules/` 40、`install/` 44。
- 取り出した後に自分で確かめる書き方が散らばっている: ID の形式チェック `preg_match('@^[1-9][0-9]*$@', getv('id'))` が 16 か所、`(int)` / `intval()` の後付けが 55 か所、`is_numeric()` / `ctype_digit()` が 4 か所。
- `getv()` だけが、空の値（`?k=`）を「無い」とみなして既定値を返す。既定値つきの `getv()` は 35 か所あり、そのうち空のときに既定値へ戻ることを期待しているものがある（例: `manager/actions/header.inc.php` の `getv('tab', 1)` と `getv('f', 'mainpane')`、`manager/actions/report/logging.static.php` の `getv('nrresults', config('number_of_logs'))`、`manager/actions/report/eventlog.dynamic.php` の `getv('period', 'latest')`、`manager/media/browser/evo/browser.php` の `getv('type', getv('Type', 'images'))`）。

## Plan of Work

M1 で型つきのヘルパーを足し、決まりを文書にする。足すのは次の型で、それぞれ `get_` / `post_` / `request_` の 3 つの受け取り元を用意する（ID 用の `*_intvar()` と同じ並び）。変換の本体は受け取り元に依らない `to_*()` に置き、`to_intvar()` と同じ形にする。

- `*_strvar($key, $default = '')`: 文字列。配列などの文字列でない値と空文字は既定値。
- `*_boolvar($key, $default = false)`: 真偽値。`'1'`・`'on'`・`'true'`・`'yes'` を true、`'0'`・`'off'`・`'false'`・`'no'` を false、それ以外は既定値。
- `*_choicevar($key, array $choices, $default)`: 選択肢の中の値だけ。それ以外は既定値。
- `*_idsvar($key)`: 正の整数の配列（`to_intvar()` を各要素に使い、0 を除く）。配列でもカンマ区切りの文字列でも受ける。
- `*_intvar()` は今のまま（正の整数、それ以外は 0）。0 や負の数が意味を持つ数値（並びのずれなど）が要る場所が置き換えの途中で見つかったら、そのときに `*_numvar()` を足すかを決め、Decision Log に書く。

M2 で `getv()` の空の値の特例をやめる。動きが変わるのは「空の値を送り、既定値つきで受けている」呼び出し元だけなので、M2 の中で既定値つきの `getv()` 35 か所をすべて確かめ、空なら既定値に戻ることを期待しているものを型つきへ置き換える。特例をやめる変更と置き換えは同じ PR にする（途中の版で動きが変わらないように）。

M3〜M6 でディレクトリごとに置き換える。置き換えの規則は次の表のとおり。生の値のまま残してよいのは、文字列として厳密に比べるだけのもの（`postv('mode') === 'save'`）と、値を加工せずに保存するもの（`db()->escape(postv('content', ''))` のように既定値 `''` を付けて渡すもの）、配列のまま扱う項目（テンプレート変数の値など）。

    今の書き方                                         置き換え先
    preg_match('@^[1-9][0-9]*$@', getv('id')) ...      get_intvar('id')（0 なら無い扱い）
    (int) postv('parent') / intval(anyv('x'))          post_intvar('parent') など
    getv('force', 0) を真偽として使う                  get_boolvar('force')
    getv('period', 'latest') を選択肢として使う        get_choicevar('period', [...], 'latest')
    anyv('depid') を ID の配列として使う               request_idsvar('depid')
    trim(postv('q')) / strtolower(getv('x'))           trim(post_strvar('q')) など

M7 で、置き換え漏れを検出する規則を `security-lint.php` に足す。対象は、生の値のヘルパーの戻り値を、既定値なしのまま文字列関数（`preg_match`・`trim`・`strpos`・`strtolower`・`explode`・`is_file` など）や `count()` に直接渡す書き方。走査範囲は `manager/`・`assets/`（`assets/plugins/*/tinymce*/` を除く）・`install/`。M3〜M6 が終わった時点で違反 0 件にし、CI で守る。

M8 で、PHP 8.5 と PHP 7.4 の検証環境で横断の確認をする。

SQL に入れる値の無害化は PDO ベースの新しい DB API（`.agent/plans/2026-07-08-pdo-migration-dbapi-refactor.md`）が受け持つ。この計画は「型が保証された値を渡す」までにし、`db()->escape()` を外すなどの変更はしない。`sessionv()` / `serverv()` / `cookiev()` はリクエストの値ではないので対象外にする（`cookiev()` は 5 か所だけなので、M5 で必要なら型つきに寄せる）。

## Concrete Steps

M1:

- 編集: `manager/includes/helpers.php` に `to_strvar()` / `to_boolvar()` / `to_choicevar()` / `to_idsvar()` と、それぞれの `get_` / `post_` / `request_` を足す。`*_intvar()` の直前のコメントを、一族全体の説明に書き直す。
- 新規: `assets/docs/request-input.md`（生の値と型つきの使い分け、空の値の扱い、置き換えの表）。AGENTS.md のドキュメントマップに足し、「スーパーグローバル変数の禁止」の項から参照する。
- 確認: 検証用イメージの PHP で、各 `to_*()` に `null`・`''`・`'0'`・`'abc'`・配列・正常な値を渡し、期待どおりの値を返すことを確かめる。PHP 7.4 は `php:7.4-cli` で同じスクリプトを流す。

    docker run --rm -v "$PWD":/app -w /app php:7.4-cli php <作業用ディレクトリ>/check-helpers.php
    期待: すべての行が PASS

M2:

- 編集: `getv()` から空の値を消す処理を取り除き、`postv()` と同じく `array_get($_GET, $key, $default)` だけにする。
- 確認: `grep -rnE "getv\('[^']+', ?[^)]" --include=*.php manager assets install` の 35 か所を 1 つずつ読み、空のときに既定値へ戻る前提のものを型つきへ置き換える。置き換えた箇所と、そのままにした理由を PR 本文に表で書く。
- 確認: 検証環境で、置き換えた画面を空の値で開き（例: `a=114&period=`、`a=13&nrresults=`）、修正前と同じ表示になることを確かめる。

M3〜M6（各マイルストーンで同じ手順）:

- 編集: 対象ディレクトリの呼び出し元を、Plan of Work の表に沿って置き換える。1 つの PR は 1 つのマイルストーン（大きければサブディレクトリ単位）にする。
- 確認: 下の Validation の巡回スクリプトを、対象の画面に絞って修正前と修正後で流し、警告が増えていないこと、パラメータを欠いた・空の・配列を送ったリクエストで警告が 0 件であることを確かめる。保存を伴う画面は、`.claude/skills/adaptive-validation-runner/scripts/smoke.js` と、その画面の保存を 1 回ずつ確かめる。

M7:

- 編集: `.github/scripts/security-lint.php` に規則（例: `raw-input`）を足す。
- 確認: `php .github/scripts/security-lint.php` が `0 violations` で終わる。わざと `trim(getv('x'))` を書いた一時ファイルで違反が出ることも確かめ、確かめた後に消す。

## Validation and Acceptance

検証環境は `.claude/skills/adaptive-validation-runner/` の手順で立てる。PHP 8.5 は `.claude/skills/adaptive-validation-runner/references/environment.md` の「ほかの PHP の版で検証するとき」の方法で `FROM php:8.5-apache` にしたイメージを使う。一覧のページ送りを通すため、管理ユーザー・Web ユーザー・リソースを各 120 件ほど足し、`php evo cache:clear` でキャッシュを作り直す（MariaDB の `seq_1_to_120` を使う。`<名前>` は検証環境の名前）。

    docker exec <名前>-db mariadb -uuser -ppassword evodb -e "
      INSERT INTO modx_manager_users (username,password) SELECT CONCAT('u',seq),'x' FROM seq_1_to_120;
      INSERT INTO modx_user_attributes (internalKey,fullname,role,email) SELECT id,CONCAT('User ',id),1,CONCAT(username,'@example.com') FROM modx_manager_users WHERE username LIKE 'u%';
      INSERT INTO modx_web_users (username,password) SELECT CONCAT('w',seq),'x' FROM seq_1_to_120;
      INSERT INTO modx_web_user_attributes (internalKey,fullname,email) SELECT id,CONCAT('Web ',id),CONCAT(username,'@example.com') FROM modx_web_users WHERE username LIKE 'w%';
      INSERT INTO modx_site_content (pagetitle,alias,parent,published,template,content,createdon,editedon) SELECT CONCAT('Doc ',seq),CONCAT('doc',seq),0,1,1,'x',UNIX_TIMESTAMP(),UNIX_TIMESTAMP() FROM seq_1_to_120;"

巡回は Playwright でログインし、画面ごとに GET した本文から `Deprecated` / `Warning` / `Notice` / `Fatal error` / `Uncaught` の行を集める（`.claude/skills/adaptive-validation-runner/scripts/lib.js` の `login()` を使う。書き方は `.claude/skills/adaptive-validation-runner/scripts/smoke.js` が見本）。巡回の後にシステムログの `level` が `error` 以上の行も確かめる。

完了とみなす観察結果:

- 管理画面の画面（`manager/index.php` の振り分けのうち `manager/actions/` を読むもの、約 70 画面）と公開側のサンプルサイトの各ページを巡回し、応答の本文とシステムログ（`temp/logs/system/`）に Deprecated / Warning / Notice / Fatal error が 0 件。
- 同じ巡回を、各画面の主なパラメータを「欠いた」「空にした」「配列にした（`id[]=1`）」の 3 通りでも流し、0 件。
- `.claude/skills/adaptive-validation-runner/scripts/smoke.js` が PHP 8.5 と標準の PHP 8.4 で全 PASS。変更したファイルが `php:7.4-cli` で構文チェックを通る。
- `php .github/scripts/security-lint.php` が `0 violations`。
- `assets/docs/request-input.md` に決まりが書かれ、AGENTS.md から参照されている。

## Idempotence and Recovery

各マイルストーンは独立した PR にし、マージ済みのマイルストーンは Progress に 1 行で記録する。途中で中断したときは、Progress の未完了のマイルストーンから再開する。M2 だけは、`getv()` の変更と呼び出し元の置き換えを同じ PR に入れる（分けると、間の版で空の値の扱いが変わる）。置き換えで動きが変わった画面が見つかったら、その PR を戻せば元に戻る（ヘルパーの追加は互換を壊さない）。

## Artifacts and Notes

- Issue: #1059
- 前段の修正: PR #1058（PHP 8.5 の非推奨の警告とモジュールの依存関係の不具合）
- 参考にした作法: Laravel の `$request->integer()` / `boolean()` / `string()` / `enum()` と `has()` / `filled()`、Symfony の InputBag の `getInt()` / `getDigits()` / `getBoolean()` / `getString()` / `getEnum()`
- 想定のコミット単位: M1 は「feat(helpers): リクエスト値を型ごとに取り出すヘルパーを足す」と「docs: リクエスト値の取り出し方の決まりを書く」。M2 は「refactor(helpers): getv() の空の値の特例をやめ、GET と POST の扱いをそろえる」。M3〜M6 は「refactor(<範囲>): リクエスト値を型つきのヘルパーで取り出す」をサブディレクトリ単位で。M7 は「ci(security-lint): リクエスト値を確かめずに文字列関数へ渡す書き方を検出する」。

## Interfaces and Dependencies

- 追加する関数（`manager/includes/helpers.php`）: `to_strvar` / `to_boolvar` / `to_choicevar` / `to_idsvar` と、`get_` / `post_` / `request_` を付けた `strvar` / `boolvar` / `choicevar` / `idsvar`。既存の `*_intvar()` / `to_intvar()` / `abort_if_invalid_id()` の動きは変えない。
- 動きが変わる関数: `getv()`（空の値をそのまま返すようになる）。同梱外のスニペットやプラグインが `getv()` の空の値の特例に頼っている場合は動きが変わるため、リリースノートに書く（Issue #1059 に `仕様変更` のラベルを付けてある）。
- 依存: なし。PDO 移行（`.agent/plans/2026-07-08-pdo-migration-dbapi-refactor.md`）とは独立に進められるが、同じ processor を触るので、並行するときはマイルストーンの範囲が重ならないようにする。
