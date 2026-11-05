# ExecPlan: リクエスト値を型ごとに取り出すヘルパーを用意し、空の値の扱いを GET と POST でそろえる

## Purpose / Big Picture

管理画面・同梱のスニペットとプラグイン・インストーラが受け取るリクエスト値（URL のクエリやフォームの送信値）を、型が保証された形で取り出すようにする。これで、パラメータを欠いた・空の・配列を送ったリクエストで、PHP 8.1〜8.5 の非推奨の警告や TypeError、想定外の SQL が出なくなる。あわせて、GET と POST で空の値の扱いが違う状態をなくし、`getv()` を `postv()` に書き換えても動きが変わらないようにする。

Issue: #1059

## Progress

- [x] (2026-11-05) M1: 型つきヘルパー 12 関数と `to_*()` 4 つを `manager/includes/helpers.php` に足し、決まりを `assets/docs/request-input.md` に書いた。`*_strvar` を security-lint の検出対象に入れた（PR は Artifacts and Notes）
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
- 2026-11-05: `getv()` の特例をやめると、既定値を省いた `getv()` も空の値で `null` ではなく `''` を返すようになる（PR #1060 のレビューで指摘）。たとえば `manager/frames/nodes.php:54` は `getv('offset') !== null` で分岐していて、`offset=&expandAll=1` は今は通常の展開に進むが、特例をやめるとページ送りの分岐に入り `expandAll` を無視する。そのため M2 の確認は既定値つきの呼び出しに限らず、`getv()` の全呼び出しを対象にする。
- 2026-11-05（M1 の自己レビュー）: `.github/scripts/security-lint.php` の sql-embed は `REQUEST_FUNCS`（`getv` / `anyv` / `postv` / `cookiev`）の名前で照合している。中身をそのまま返す `*_strvar()` に置き換えると SQL への埋め込みが検出から外れるため、M1 で `*_strvar` を `REQUEST_FUNCS` に足した。`*_intvar` / `*_boolvar` / `*_choicevar` / `*_idsvar` は返す値がコードで決まるので足さない。
- 2026-11-05（M1 の自己レビュー）: ID の一覧を読む既存の処理は動きが揃っていない。`manager/processors/document/move_document.processor.php` は 1 つでも不正な id があればエラーで止め、`manager/actions/element/mutate_module_resources.dynamic.php` は読めた id だけを使う。`*_idsvar()` は読めない要素を黙って捨てるので、全体を拒否したい処理は置き換えないか、要素数を比べる（`assets/docs/request-input.md` に記載）。

## Decision Log

- 2026-11-05 / yamamoto・Claude: 取り出し口の形は、既存の `*_intvar()` を広げる（受け取り元の接頭辞 `get_` / `post_` / `request_` × 型）。代替案の `request()->query()->int('id')` のような 1 つの入口は Symfony に近いが、既存の約 150 か所も置き換えになり、今の作法から外れるため採らない。
- 2026-11-05 / yamamoto・Claude: 空の値は「生の値はそのまま、型つきは既定値」にする。`getv()` / `postv()` / `anyv()` はキーがあれば空文字をそのまま返し、既定値はキーが無いときだけ使う（Symfony の InputBag と同じ）。型つきの取り出し口は、空・形式違い・配列などの想定外の型を、すべて既定値にする。代替案の「すべて空なら既定値」は、保存で項目を空にしたい場合に既定値が入る恐れがあり、`postv()` の約 540 か所の確認が重いため採らない。「型つきも空はそのまま」は、型つきで空を受けたときの値が型ごとにばらつくため採らない。
- 2026-11-05 / Claude（M1 の自己レビュー）: `*_choicevar()` の `$default` は必須にする。省けると形式違いのときに `null` が返り、この計画が防ぎたい「null を文字列関数へ渡す」形がまた生まれるため。
- 2026-11-05 / Claude（M1 の自己レビュー）: `*_idsvar()` は、文字列で受けたときだけカンマで分けた各要素の前後の空白を除く（`'3, 4'` は `[3, 4]`）。配列の要素は除かない。既存の `move_document.processor.php` が各要素を `trim()` してから読んでいるのに合わせた。1 つの値を読む `*_intvar()` は今のまま空白を許さない。

## Outcomes & Retrospective

## Context and Orientation

用語:

- リクエスト値: ブラウザが送ってくる値。URL の `?id=5` のようなクエリ（GET）と、フォームの送信内容（POST）がある。PHP では `$_GET` / `$_POST` / `$_REQUEST` に入るが、このプロジェクトでは直接触らず、ヘルパーを通す（AGENTS.md の「スーパーグローバル変数の禁止」）。
- 生の値のヘルパー: `getv($key, $default)`（GET）、`postv()`（POST）、`anyv()`（GET と POST の両方）。値を変換せずに返す。キーを省略すると全件を配列で返す。中身は `array_get()`。
- 型つきのヘルパー: 決まった型の値だけを返し、それ以外は既定値を返すヘルパー。今は ID 用の `*_intvar()` だけがある。

関係するファイル:

- `manager/includes/helpers.php`: `array_get()`（373 行付近、4 つ目の引数 `$validate` で値を確かめられる）、`*_intvar()` と `to_intvar()`、M1 で足した型つきヘルパー（420〜600 行付近）、`getv()` / `postv()` / `anyv()`（630〜680 行付近）。行番号は 2026-11-05 の M1 の時点のもの。
- `.github/scripts/security-lint.php`: processor の中でリクエスト値を無害化せずに SQL へ入れる書き方などを検出する。CI（`.github/workflows/security-lint.yml`）で動く。
- 文書: ヘルパーの決まりは今は AGENTS.md の「スーパーグローバル変数の禁止」にしか無い。

今の状態（2026-11-05、grep による数）:

- 呼び出し元は `getv` 238・`postv` 544・`anyv` 235 か所。ディレクトリごとでは `manager/actions/` 360、`manager/processors/` 363、`manager/includes/` 66、`manager/media/` 32、`manager/frames/` 14、`assets/snippets/` 37、`assets/plugins/` 46、`assets/modules/` 40、`install/` 44。
- 取り出した後に自分で確かめる書き方が散らばっている: ID の形式チェック `preg_match('@^[1-9][0-9]*$@', getv('id'))` が 16 か所、`(int)` / `intval()` の後付けが 55 か所、`is_numeric()` / `ctype_digit()` が 4 か所。
- `getv()` だけが、空の値（`?k=`）を「無い」とみなして既定値（既定値を省いたときは `null`）を返す。この特例に頼っている形は 2 つある。1 つは既定値を省いて `null` と比べるもので、直接比べている箇所は `manager/frames/nodes.php:54` の `getv('offset') !== null` と `manager/actions/document/mutate_content/functions.php:504` の `getv('pid') === null`（変数に入れてから比べる形は M2 で追う）。もう 1 つは既定値つきの `getv()`（35 か所）で、空のときに既定値へ戻ることを期待しているもの（例: `manager/actions/header.inc.php` の `getv('tab', 1)` と `getv('f', 'mainpane')`、`manager/actions/report/logging.static.php` の `getv('nrresults', config('number_of_logs'))`、`manager/actions/report/eventlog.dynamic.php` の `getv('period', 'latest')`、`manager/media/browser/evo/browser.php` の `getv('type', getv('Type', 'images'))`）。

## Plan of Work

M1 で型つきのヘルパー（`*_strvar` / `*_boolvar` / `*_choicevar` / `*_idsvar`）を足し、決まりを文書にする（M1 で完了）。それぞれ `get_` / `post_` / `request_` の 3 つの受け取り元を用意し（ID 用の `*_intvar()` と同じ並び）、変換の本体は受け取り元に依らない `to_*()` に置く。各ヘルパーの戻り値の規則、空白と大文字小文字の扱い、0 や負の数が意味を持つ値の扱い（`*_numvar()` は置き換えの途中で要るとわかったら足すかを決め、Decision Log に書く）は、`assets/docs/request-input.md` を正本とする。

M2 で `getv()` の空の値の特例をやめる。動きが変わるのは、空の値が送られたときに、戻り値が既定値（省いたときは `null`）から `''` に変わる呼び出し元である。既定値の有無に関わらず `getv()` の全呼び出し（238 か所）を確かめ、空の値と無い値で動きが分かれるもの（既定値に戻ることを期待する、`null` と比べる、戻り値を入れた変数を `null` や `isset()` で判定する）を型つきへ置き換えるか、`=== null` の判定を「空か無いか」の判定に直す。特例をやめる変更と置き換えは同じ PR にする（途中の版で動きが変わらないように）。

M3〜M6 でディレクトリごとに置き換える。置き換えの規則（置き換えの表、生の値のまま残してよい形、`*_boolvar()` や `*_idsvar()` に置き換える前に確かめること）は `assets/docs/request-input.md` を正本とし、ここには写さない。

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
- 確認: `grep -rnE "\bgetv\(" --include=*.php manager assets install`（tinymce を除く 238 か所）を 1 つずつ読み、空の値と無い値で動きが分かれるものを Plan of Work の規則で直す。直接 `null` と比べる箇所は `grep -rnE "getv\('[^']+'\) *(!==|===) *null|is_null\(getv\(" --include=*.php manager assets install` で拾えるが、変数に入れてから比べる形はこれで拾えないので、全呼び出しを読む。直した箇所と、そのままにした理由を PR 本文に表で書く。
- 確認: 検証環境で、`manager/frames/nodes.php` を `offset=&expandAll=1` で呼び、修正前と同じくツリーをすべて展開した結果が返ることを確かめる。
- 確認: 検証環境で、置き換えた画面を空の値で開き（例: `a=114&period=`、`a=13&nrresults=`）、修正前と同じ表示になることを確かめる。

M3〜M6（各マイルストーンで同じ手順）:

- 編集: 対象ディレクトリの呼び出し元を、`assets/docs/request-input.md` の置き換えの表に沿って置き換える。1 つの PR は 1 つのマイルストーン（大きければサブディレクトリ単位）にする。
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
- 段階ごとのブランチ（`origin/main` から `git switch -c` で作り、Issue には紐づけない。後の段階は前の段階のブランチの上に積み、PR の base も前の段階のブランチにする。本人の指示、2026-11-05）: M1 は `feat/1059-m1-typed-helpers`。
- M1 の確認ケース（`to_*()` を直接呼び、php:7.4-cli・8.4-cli・8.5-cli で ALL PASS、警告 0。`error_reporting(E_ALL)` で警告を FAIL にし、`require 'manager/includes/helpers.php'` だけで読み込める）。M2 以降で境界を確かめ直すときは、同じ表で作り直す。
    to_strvar:   null→'' / ''→'' / ('', 'x')→'x' / 'abc'→'abc' / ['a']→既定 / 5→既定 / '0'→'0' / ' '→' '
    to_boolvar:  null→false / ('', true)→true / true→true / 1→true / (2, 'd')→'d' / '1'→true / ' On '→true / 'OFF'→false
                 'yes'→true / 'no'→false / ('abc', 'd')→'d' / (['1'], 'd')→'d' / (1.0, 'd')→'d'
    to_choicevar: ('a', ['a','b'], 'd')→'a' / ('z', …)→'d' / ('', …)→'d' / ('2', [1,2,3], 0)→2（元の型）/ (3, ['1','3'], null)→'3'
                 (['a'], ['a'], 'd')→'d' / ('a', [0], 'd')→'d' / ('01', [1,2], 0)→0 / 引数 2 つ→ArgumentCountError
    to_idsvar:   null→[] / ''→[] / '1,2,3'→[1,2,3] / '3, 4'→[3,4] / ' 3 , x, 4'→[3,4] / '3'→[3] / ' 3 '→[3]
                 '3,2,abc,0,3,-1,,5'→[3,2,5] / ['4',5,'x','4',['1']]→[4,5] / [' 3']→[] / 5→[] / ['0','7']→[7]
    受け取り元:  12 関数が存在する。$_GET / $_POST / $_REQUEST に値を入れて get_ / post_ / request_ の各型が上と同じ規則で返す
- 想定のコミット単位: M1 は「feat(helpers): リクエスト値を型ごとに取り出すヘルパーを足す」と「docs: リクエスト値の取り出し方の決まりを書く」。M2 は「refactor(helpers): getv() の空の値の特例をやめ、GET と POST の扱いをそろえる」。M3〜M6 は「refactor(<範囲>): リクエスト値を型つきのヘルパーで取り出す」をサブディレクトリ単位で。M7 は「ci(security-lint): リクエスト値を確かめずに文字列関数へ渡す書き方を検出する」。

## Interfaces and Dependencies

- 追加する関数（`manager/includes/helpers.php`）: `to_strvar` / `to_boolvar` / `to_choicevar` / `to_idsvar` と、`get_` / `post_` / `request_` を付けた `strvar` / `boolvar` / `choicevar` / `idsvar`。既存の `*_intvar()` / `to_intvar()` / `abort_if_invalid_id()` の動きは変えない。
- 動きが変わる関数: `getv()`（空の値をそのまま返すようになる）。同梱外のスニペットやプラグインが `getv()` の空の値の特例に頼っている場合は動きが変わるため、リリースノートに書く（Issue #1059 に `仕様変更` のラベルを付けてある）。
- 依存: なし。PDO 移行（`.agent/plans/2026-07-08-pdo-migration-dbapi-refactor.md`）とは独立に進められるが、同じ processor を触るので、並行するときはマイルストーンの範囲が重ならないようにする。
