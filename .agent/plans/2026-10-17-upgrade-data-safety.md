# ExecPlan: アップグレードで利用者のデータや設定を壊す不具合を直す（#654, #655, #657, #658, #659, #656）

## Purpose / Big Picture

古い版で作ったサイトを新しい版にアップグレードしても、利用者が決めた設定（DB の文字セット、ロールの名前と権限、無効にした同梱プラグイン、プロパティの値）がそのまま残るようにする。あわせて、主キー必須の DB でも設定画面が開け、DB のパスワードや管理者名に記号があってもインストールできるようにする。1.2 系から 1.3.0 へのアップグレードで踏むので、1.3.0 の正式リリース前に直す。

## Progress

- [x] (2026-10-17) ExecPlan を作成（6 件の原因を確認し、M1〜M5 に分けた。要相談 4 件は Decision Log の先頭）
- [x] (2026-10-17) 要相談 Q1〜Q4 に本人の確認を得た（選択肢の UI で回答。すべて推奨の案。Decision Log の「決まったこと」を参照）
- [ ] M1: #654 utf8mb4 に変換したら、接続文字セットも utf8mb4 で書き出す
- [ ] M2: #655 ロール ID 1 と権限をアップグレードで初期値に戻さない（Q3 は案 i に決定）
- [ ] M3: #657 無効にした同梱プラグインを入れ直さない、#658 プロパティの値を切らない（Q1 は案 B、Q2 は案 a に決定）
- [ ] M4: #659 主キーが正しいときは付け直さない
- [ ] M5: #656 設定ファイルと初期データに入れる値を、文脈に合わせてエスケープする。最後に 1.2.2J からの通しの確認を行う

## Surprises & Discoveries

計画時（2026-10-17、ceeff2009 で確認）の発見:

- #655 は `install/sql/fix_settings.php` を直すだけでは直りきらない。管理画面側の `manager/includes/upgrades/upgrades.php` の `update_tbl_user_roles()`（153〜173 行）も、版が変わるたびに（設定画面を開いたときに）、`save_role=1` のロールすべてへ 12 個の権限を付け直している。ロール ID 1 の初期値も `save_role=1` なので、利用者が外した `empty_trash` などは、インストーラを直してもここで付き直る。この関数は 2017-02 の f63729c25（`move_document` の追加）から変わっていない。12 個の列は、1.0.20J（タグ `release-1.0.20J`）の `install/sql/create_tables.sql` にすべてある（1.0.19J には `move_document` が無い。`git show release-1.0.19J:install/sql/create_tables.sql` で確認）。
- #657: プラグインのアップグレードは、版が同じでも、同名の有効な行を無効にして新しい行を入れる（`install/processors/prc_insPlugins.inc.php` 85 行のコメント。利用者の手直しを控えとして残すため）。そのため、無効な同名の行があることだけでは、利用者が無効にしたとは言えない（過去のアップグレードの控えも無効な行）。利用者が無効にした場合は「有効な行が 1 つも無く、無効な行だけがある」形になる。
- #657: `@installset base` の要素をチェックの有無にかかわらず入れる規則（`install/functions.php` 930 行）は、a708bdafd（2025-10-30「Ensure base packages install without manual selection」）で意図して入れたもの。変えるかどうかは要相談 Q2 とした。
- #654: MariaDB 10.6 以降と MySQL 8.0.30 以降では、照合順序の名前が `utf8mb3_general_ci` になる。`isUpGradeable()` は照合順序の `_` より前を文字セットとして使うので、セッションの値は `'utf8mb3'` になり、書き出される設定も `'utf8mb3'` になる（Issue の `'utf8'` と同じ不具合で、値だけが違う）。`convert2utf8mb4::updateConfigIncPhp()` は `'utf8'` だけを置き換えるので、`'utf8mb3'` は置き換えない。
- #654: すでにこの不具合を踏んだサイト（テーブルは utf8mb4 で、設定が utf8 のまま）は、次のアップグレードで直る。`isUpGradeable()` が `site_content.content` の照合順序（utf8mb4）から文字セットを決めるため。ただ、その間に `?` で保存された文字は戻せない。
- #656: 検証環境（`.claude/skills/adaptive-validation-runner/scripts/compose.validation.yml`）は、アプリのコンテナに `DB_USERNAME` / `DB_PASSWORD` などの環境変数を渡す。`config.inc.php` は `env('DB_PASSWORD', '<書き出した値>')` の形なので、環境変数があればそちらが使われ、書き出した値の誤りは検証環境では表に出ない。書き出した値は、ファイルを直接読んで確かめる。
- #656: `evo()->parseText()` は、テンプレートの中のタグごとに置き換えを繰り返すので、値の中に別のタグと同じ文字列（`[+dbase+]` など）があると、それも置き換わる。設定ファイルの書き出しは、一度に置き換える `strtr()` にする。
- #659: MariaDB には `sql_require_primary_key` が無い。MySQL 公式イメージは Docker Engine 29 系で取得できない（`.claude/skills/adaptive-validation-runner/references/environment.md` の「既知の落とし穴」）。代わりに MariaDB の `innodb_force_primary_key` と general_log（発行された SQL の記録）で確かめる。
- #658: 修正前の `propUpdate()` の出力をホストの PHP 8.4 で確かめた。`&tpl=テンプレート;string;@CODE:<a href="[+url+]">[+title+]</a>` は `&tpl=テンプレート;string;@CODE:<a href ` になり、`&a=Show;list;yes,no;yes &b=Show;list;yes,no;yes` は `&a=Show;list;yes,no;yes ` になる。`array_unique()` は値で重複を除くので、名前の重複を除く役には立っていない（配列のキーはもともと重複しない）。
- 範囲外（起票しない）: `install/functions.php` 624 行の関数 `parseProperties()` は、どこからも呼ばれておらず、同じ `explode('=')` の問題がある。接続テスト（`install/connection.databasetest.php` 51 行）はテーブル接頭辞の文字を確かめていない。アップグレード時、設定ファイルに書く DB の接続情報は、環境変数があればその値になる（`isUpGradeable()` が `config.inc.php` を読み込んだ結果をセッションに入れるため）。
- 検証環境の管理者 `admin` はロール 2（ウェブマスター、`save_role=1`）。ロール 1（Administrator）には、初期状態ではユーザーがいない（`install/sql/default_settings.sql` 46 行）。

## Decision Log

### 決まったこと（要相談 Q1〜Q4 の回答）

2026-10-17 に本人が選択肢の UI で回答した。結果は次のとおり（Q1: 案 B、Q2: 案 a、Q3: 案 i、Q4: 推奨どおり直さない。すべて推奨の案）。以下の各項目は、選択肢と推奨の理由の記録として残す。

- 回答 Q1: 案 B（オプション画面に出し、既定でチェックを外して「このサイトでは無効にしてあります」と添える）
- 回答 Q2: 案 a（アップグレードではチェックを尊重し、新規インストールでは今のまま base を必ず入れる）
- 回答 Q3: 案 i（前の版が 1.0.20J より古いときだけ、管理画面側の `update_tbl_user_roles()` を実行する）
- 回答 Q4: すでに壊れたサイトのデータは直さない（リリース記事で手直しを案内する）

- Q1（#657）利用者が無効にした同梱プラグイン（有効な行が無く、無効な同名の行だけがある）を、アップグレードでどう扱うか。
  - 案 A: オプション画面に出さず、入れない。
  - 案 B（推奨）: オプション画面には出すが、既定ではチェックを外し、「このサイトでは無効にしてあります」と添える。チェックを入れたときだけ、新しい版を有効な状態で入れる。
  - 案 C: 新しい版を、無効な状態で入れる。
  - 推奨の理由: 何もしなければ利用者の意図（止めておく）が保たれ、使いたくなったときはインストーラから最新版を入れ直せる。同梱プラグインは最新の本体だけをサポートするので、古いコードの行を管理画面で有効に戻すしか道が無い案 A より安全。案 C は、アップグレードのたびに無効な同名の行が増え、どれを有効にすればよいか分からなくなる。案 B は Q2 で「アップグレードではチェックを尊重する」（案 a か b）を選んだときだけ成り立つ。Q2 が案 c なら、Q1 は案 A にする。
- Q2（#657）`@installset base` の要素（QuickManager・TinyMCE7・ManagerManager・FMP・Wayfinder・Ditto・eForm・TopicPath・WebLogin・DocManager・mm_rules）を、オプション画面のチェックを外しても入れる今の規則をどうするか。
  - 案 a（推奨）: アップグレードではチェックを尊重する。新規インストールでは今のまま必ず入れる。
  - 案 b: 新規インストールとアップグレードの両方で、チェックを尊重する。
  - 案 c: 今のまま（チェックにかかわらず入れる）。
  - 推奨の理由: アップグレードの画面は既定でチェックが入っているので、触らなければ今と同じ結果になる。チェックを外すのは「この要素には触らないで」という意思表示で、それを無視する理由が無い。新規インストールで必ず入れる規則は a708bdafd で意図して入れたものなので、その範囲（初めて入れるとき）は残す。
- Q3（#655）Issue の直し方の案（`install/sql/fix_settings.php` だけ）に加えて、管理画面側の `update_tbl_user_roles()` も直すか。
  - 案 i（推奨）: 直す。前の版が 1.0.20J より古いときだけ実行する。
  - 案 ii: 関数ごと消す。
  - 案 iii: 直さない（Issue の案のとおり）。
  - 推奨の理由: 直さないと、ロール ID 1 と `save_role=1` のロールに、外した権限が版を上げるたびに付き直り、#655 の症状が残る（Surprises & Discoveries の 1 つ目）。この関数は、12 個の列が足されたときに権限を配るためのもので、列がそろった 1.0.20J 以降のサイトでは役目が無い。消すと、1.0.20J より古いサイトをインストーラを通さずに上げたときに、配られなくなる。
- Q4（全体）すでに壊れた既存のサイトのデータを、アップグレードで直すか。
  - 推奨: 直さない。初期値に戻されたロールの名前や権限、入り直して有効になったプラグイン、切れたプロパティの値、`?` になった文字は、元の値が DB に残っておらず、利用者の意図とも見分けられない。#654 の設定の文字セットは、直したあとの次のアップグレードで自動で直る。リリース記事で「以前のアップグレードで戻った設定は、手で直してください」と案内する。

### 決めたこと

- 2026-10-17 / planner: マイルストーンは、触るファイルと確かめ方がまとまる単位で 5 つに分け、Issue の並び（ロードマップの `Issue:` の順）で進める。#657 と #658 は、どちらもアップグレードでの要素の入れ直し（`install/functions.php` と `install/processors/`）で、同じ手順で確かめられるので M3 にまとめた。DB スキーマは変えない。
- 2026-10-17 / planner: #654 は、変換が終わったらセッションの文字セットと照合順序を `utf8mb4` / `utf8mb4_general_ci` に更新する（Issue の案 1）。設定ファイルの書き出しを変換より前に移す案 2 は採らない。書き出しは DB の処理が成功したあとに置かれていて（`install/instprocessor.php` 277 行で失敗なら止まる）、前に移すと失敗したアップグレードでも設定ファイルが書き換わるため。`db()->exec()` は失敗すると `messageQuit()` で止まるので、`convert2utf8mb4()` が最後まで進めば変換は成功している。あわせて `updateConfigIncPhp()` が `'utf8mb3'` も置き換えるようにする（変換のあとでアップグレードが失敗し、設定ファイルを書き出さない経路で使われる）。
- 2026-10-17 / planner: #655 のロール ID 1 は、行が無いときだけ入れる（Issue の案）。権限の列を足したときは、その列だけをロール ID 1 に付ける（`addColumnIfNotExists()` が「足した」ことを返すようにする）。`$data` の配列を列の一覧から作り直す案は、今回の不具合に要らないので採らない。
- 2026-10-17 / planner: #658 は、`explode('=', $v, 2)` にし、`array_unique()` を外し、`=` を含まない断片を飛ばす。本体の `DocumentParser::parseProperties()`（`manager/includes/document.parser.class.inc.php` 5329 行）と同じ分け方にそろえる。値の中の `&` は、本体と同じく `%26` で表す前提なので、`&` での分割は変えない。
- 2026-10-17 / planner: #659 は、主キーの列を `SHOW KEYS ... WHERE Key_name='PRIMARY'` の `Seq_in_index` 順で読み、目的の列と同じなら何もしない。違えば `DROP PRIMARY KEY, ADD PRIMARY KEY (...)` を 1 つの `ALTER TABLE` にする。無ければ `ADD PRIMARY KEY` だけを行う（Issue の案）。2 つのテーブルで同じ処理なので、`manager/includes/upgrades/upd_db_structure.php` の中に関数を 1 つ作る。
- 2026-10-17 / planner: #656 の設定ファイルは、値を PHP のリテラル（`var_export()` の結果）として埋め込み、`strtr()` で一度に置き換える。`install/tpl/config.inc.tpl` のタグを囲む引用符は外す。書き出しは `install/functions.php` の関数にまとめ、ホストの PHP だけで確かめられるようにする。`var_export()` は文字列を `'utf8mb4'` のような単引用符で出すので、`convert2utf8mb4` の文字列の置き換えはそのまま働く。管理者名・メール・言語名は、`SqlParser::intoDB()` の中で SQL を組み立てる直前に `db()->escape()` する（AGENTS.md の「DB操作のセキュリティ」）。テーブル接頭辞（`{PREFIX}`）は識別子で、`db()->escape()` の対象ではないので変えない。
- 2026-10-17 / planner: DocumentParser の `executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()` には影響しない（変えるのはインストーラと、管理画面の設定画面から呼ばれるアップグレード処理だけ）。キャッシュは、インストーラの最後の `evo()->clearCache()`（`install/instprocessor.php` 488 行）と、`manager/includes/upgrades/upgrades.php` の `evo()->clearCache()` で作り直されるので、追加の無効化は要らない。
- 2026-10-17 / planner: 動きが変わる点（ロールが戻らない、権限が付き直らない、無効にしたプラグインが入り直さない、チェックを外した要素を入れない、変換後の接続文字セット）は、PR 本文の「動きが変わる点」に書き、リリース記事の候補にする。

## Outcomes & Retrospective

実装後に記載

## Context and Orientation

用語:

- **アップグレード**: 古い版で動いているサイトのファイルを新しい版で上書きし、インストーラ（`install/`）を「アップグレード」として実行すること。インストーラは、`manager/includes/config.inc.php` があり、主要なテーブルがそろっていればアップグレードとして動く（`install/functions.php` の `isUpGradeable()`）。実行には管理画面へのログインが要る（`guardUpgradeAccess()`）。
- **アップグレードの 2 段階**: 1 段目はインストーラで、テーブルの作成・列の追加（`install/sql/create_tables.sql`、`install/sql/fix_settings.php`）、utf8mb4 への変換、設定ファイルの書き出し、同梱の要素の入れ直しを行う（`install/instprocessor.php`）。2 段目は、そのあと管理画面で最初に「グローバル設定」（`a=17`）を開いたときで、DB の `settings_version` が本体の版と違えば `manager/includes/upgrades/upgrades.php` と `manager/includes/upgrades/upd_db_structure.php` が動く（`manager/actions/tool/mutate_settings.dynamic.php` 23 行。ホームを開くと `manager/actions/main/welcome.static.php` 32 行が設定画面へ移す）。設定を保存すると `settings_version` が今の版になり、2 段目は以後動かない。
- **セッションの値**: インストーラは、画面をまたいで使う値（DB の接続情報、文字セット、選んだ要素など）を PHP のセッションに入れる（`sessionv('*キー', 値)` で書き、`sessionv('キー')` で読む）。
- **文字セットと照合順序**: utf8（MySQL の古い名前。今は utf8mb3）は 3 バイトまでの文字しか扱えず、絵文字や一部の漢字（4 バイトの文字）は `?` になる。utf8mb4 は 4 バイトの文字も扱える。照合順序（`utf8mb4_general_ci` など）は並べ方の規則で、名前の `_` より前が文字セット。テーブルが utf8mb4 でも、接続の文字セット（`$database_connection_charset`）が utf8 なら、4 バイトの文字は送る途中で壊れる。
- **ロール**: 管理画面のユーザーに付ける権限の組（`user_roles` テーブル）。列 `id`・`name`・`description` のほかは、すべて権限の有無（0 か 1）の列。ID 1 は Administrator。
- **同梱プラグイン・`@installset base`**: 配布物に入っていて、インストーラが入れるプラグインなど（`assets/plugins/*/*.tpl` などのファイル先頭の docblock に名前・版・プロパティを書く。`install/setup.info.php` が読む）。`@installset base` は標準で入れる印。プラグインは同じ名前の行が複数あってよく、有効な行（`disabled=0`）が動く。
- **プロパティ**: スニペット・プラグイン・モジュールの設定値。`&名前=ラベル;種類;既定値` を空白でつないだ文字列で、DB の `properties` 列に入る。
- **主キー**: テーブルの行を一意に決める列の組。`sql_require_primary_key=ON` の MySQL では、主キーの無いテーブルを作る・主キーを外す操作がエラーになる。
- **検証環境**: `.claude/skills/adaptive-validation-runner/` の Docker 環境（`.claude/skills/adaptive-validation-runner/references/environment.md` が正本）。作業ツリーを `/var/www/html` にマウントし、DB は MariaDB 11（DB `evodb`、ユーザー `user` / `password`、接頭辞 `modx_`）。管理者は `admin` / `Passw0rd!2026`。

対象のコード（行番号は ceeff2009 時点）:

- `install/instprocessor.php`: 271〜274 行で utf8mb4 へ変換し、316〜330 行で `install/tpl/config.inc.tpl` から設定ファイルを作り直して上書きする。321 行がセッションの文字セットを使う（#654）。319〜320 行が DB のユーザー名とパスワードに `db()->escape()` をかけて埋め込む（#656）。266〜269 行で `install/sql/fix_settings.php` を、新規インストールとアップグレードの両方で読み込む（#655）。403〜408 行で要素を入れ直す。
- `install/functions.php`: `compare_check()`（86 行。プラグインは 99〜101 行で有効な行だけを探す、#657）、`propUpdate()`（361〜390 行、#658）、`isUpGradeable()`（549 行。600〜601 行で照合順序から文字セットをセッションに入れる、#654）、`shouldInstallElement()`（918〜934 行。930 行が base の規則、#657）、`convert2utf8mb4()`（952〜989 行、#654）。
- `install/convert2utf8mb4.php`: 変換の部品。`updateConfigIncPhp()`（25〜36 行）が設定ファイルの `'utf8'` を `'utf8mb4'` に置き換える。
- `install/sql/fix_settings.php`: `addColumnIfNotExists()`（3〜22 行）、`user_roles` の列の追加（24〜37 行）、ロール ID 1 の `REPLACE INTO`（73〜161 行、#655）。ロール ID 1 を入れるのはここだけ（`install/sql/default_settings.sql` は ID 2 以降）。
- `manager/includes/upgrades/upgrades.php`: `run_update()`（15〜54 行）が `update_tbl_user_roles()`（153〜173 行）を版にかかわらず呼ぶ（#655）。前の版は `settings_version` を `j`・`rc` などを除いて `version_compare()` で比べる（19〜23 行）。
- `install/setup.info.php`: プラグインの候補（112〜165 行）。アップグレードで `compare_check()` が `same` なら候補から外す（145 行）。
- `install/actions/options.php`: オプション画面。`block_plugins()`（265 行）と `is_check()`（312 行。初めて開いたときは全部チェック）。選んだ要素は `install/actions/summary.php` 10〜17 行でセッションに入る。
- `install/processors/prc_insPlugins.inc.php`: プラグインの入れ直し。有効な同名の行が無ければ新しく入れる（67〜83 行）。`propUpdate()` の呼び出し元は、ほかに `install/processors/prc_insSnippets.inc.php` 52 行と `install/processors/prc_insModules.inc.php` 52 行。
- `manager/includes/upgrades/upd_db_structure.php`: 177〜202 行で `site_plugin_events` と `site_tmplvar_templates` の主キーを毎回外して付け直す（#659）。`db()->query()` は失敗すると `messageQuit()` で止まる。
- `install/tpl/config.inc.tpl`: 5〜14 行で値を `'[+database_password+]'` のように単引用符で囲む（#656）。使うのは `install/instprocessor.php` だけ。
- `install/sqlParser.class.php`: 53〜64 行で `{ADMINNAME}`・`{ADMINEMAIL}`・`{ADMINFULLNAME}`・`{MANAGERLANGUAGE}` をエスケープせずに置き換える（#656）。埋め込み先は `install/sql/default_settings.sql` の 8・22・25・41・46 行で、すべて単引用符の中。

テストスイートは無い。PHP の構文は `php -l <ファイル>`、関数単体はホストの `php -r`（`install/functions.php` は読み込んでも `ini_set()` 以外の処理をしない）、画面と DB は検証環境で確かめる。

## Plan of Work

M1（#654）: `convert2utf8mb4()` の最後（`updateConfigIncPhp()` の前）で、`sessionv('*database_charset', 'utf8mb4')` と `sessionv('*database_collation', 'utf8mb4_general_ci')` を行う。途中で `return` する経路（utf8mb4 が使えない、既定の文字セットが取れない）では更新しない。`updateConfigIncPhp()` の置き換えを `'utf8'` と `'utf8mb3'` の両方にする。書き出しは今の位置（321 行）のまま、更新されたセッションの値を使う。

M2（#655）: `install/sql/fix_settings.php` の `REPLACE INTO` をやめ、ID 1 の行が無いときだけ `db()->insert()` で入れる（値は `db()->escape()` を通す）。`addColumnIfNotExists()` は列を足したら `true` を返すようにし、`user_roles` の権限の列を足したときは `UPDATE ... SET <列>=1 WHERE id=1` を行う。Q3 が案 i なら、`run_update()` の `update_tbl_user_roles()` の呼び出しを `version_compare($pre_version, '1.0.20', '<')` の中へ移す（案 ii なら関数と呼び出しを消す、案 iii なら触らない）。

M3（#657・#658）: `propUpdate()` を Decision Log のとおりに直す。#657 は Q1・Q2 の回答で中身が決まる。推奨（Q1 案 B・Q2 案 a）の場合:
`compare_check()` で、プラグインの有効な行が無く、同名の無効な行があるときは `'disabled'` を返す（今は有効な行が無いと `getRow()` が偽を返したまま版を比べ、`'diff'` になる）。`install/setup.info.php` は、アップグレードで `'disabled'` のプラグインを候補に残し、印（例: `'user_disabled' => true`）を付ける。`install/actions/options.php` の `block_plugins()` は、印のある候補を、初めて開いたときの既定でチェックを外し、文言を添える（文言は `install/langs/english.inc.php` と `install/langs/japanese-utf8.inc.php` に足す）。`shouldInstallElement()` の base の規則は、アップグレードでは使わない（`sessionv('is_upgradeable')` が真なら、選んだものだけを入れる）。
Q1 が案 A の場合は、`install/setup.info.php` で `'disabled'` を `'same'` と同じく候補から外すだけで、`install/actions/options.php` と言語ファイルは触らない。

M4（#659）: `manager/includes/upgrades/upd_db_structure.php` に、テーブル名と列の配列を受け取り、主キーを目的の形にそろえる関数を 1 つ作り、177〜182 行と 197〜202 行をその呼び出しに置き換える。列名と表名は固定の値だけを渡す。

M5（#656）: `install/functions.php` に、テンプレートの文字列と値の配列を受け取り、`[+キー+]` を `var_export($値, true)` に `strtr()` で一度に置き換えた文字列を返す関数を作る。`install/instprocessor.php` 316〜328 行はこの関数で設定ファイルの中身を作り、`db()->escape()` をやめる。`install/tpl/config.inc.tpl` のタグを囲む引用符を外す（`$lastInstallTime` は数値のまま、`$https_port` は文字列 `'443'` のまま出るようにする）。`install/sqlParser.class.php` の `intoDB()` で、管理者名・メール・フルネーム・言語名を `db()->escape()` してから置き換える。

## Concrete Steps

各マイルストーンは、作業ツリー `/home/yamamoto/ghq/github.com/modxcms-jp/evolution-jp/.claude/worktrees/issue-654`（以下 `<wt>`）で行う。git は `git -C <wt> ...` の形で実行する。PHP を変えたら、変えたファイルごとに `php -l <wt>/<ファイル>` を実行し、`No syntax errors detected` を確かめる。

M1:

- 編集: `install/functions.php`（`convert2utf8mb4()`）、`install/convert2utf8mb4.php`（`updateConfigIncPhp()`）
- 確認: Validation and Acceptance の M1
- コミット案: `fix(install): utf8mb4 に変換したら接続文字セットも utf8mb4 で書き出す`

M2（本人の確認済み（2026-10-17、Decision Log を参照）。Q3 は案 i）:

- 編集: `install/sql/fix_settings.php`、`manager/includes/upgrades/upgrades.php`（Q3 が案 i か ii のとき）
- 確認: 1.0.20J の判定の根拠を確かめる。次のコマンドが 12 を返す（12 個の列がすべて 1.0.20J の初期テーブルにある）。続けて Validation and Acceptance の M2

        git -C <wt> show release-1.0.20J:install/sql/create_tables.sql | grep -c -E '(view_unpublished|publish_document|move_document|edit_chunk|new_chunk|save_chunk|delete_chunk|import_static|export_static|empty_trash|remove_locks|view_schedule)'

- コミット案: `fix(install): アップグレードでロール ID 1 と権限を初期値に戻さない`

M3（本人の確認済み（2026-10-17、Decision Log を参照）。Q1 は案 B、Q2 は案 a）:

- 編集: `install/functions.php`（`propUpdate()`・`compare_check()`・`shouldInstallElement()`）、`install/setup.info.php`、`install/actions/options.php`、`install/langs/english.inc.php`、`install/langs/japanese-utf8.inc.php`（後ろの 3 つは Q1 が案 B のときだけ）
- 確認: Validation and Acceptance の M3
- コミット案（2 つに分ける）: `fix(install): アップグレードでプロパティの値を途中で切らない`、`fix(install): 無効にした同梱プラグインをアップグレードで入れ直さない`

M4:

- 編集: `manager/includes/upgrades/upd_db_structure.php`
- 確認: Validation and Acceptance の M4
- コミット案: `fix(upgrade): 主キーが正しいときは付け直さない`

M5:

- 編集: `install/functions.php`（設定ファイルを作る関数を足す）、`install/instprocessor.php`、`install/tpl/config.inc.tpl`、`install/sqlParser.class.php`
- 確認: Validation and Acceptance の M5 と、最後に「通しの確認」
- コミット案: `fix(install): 設定ファイルと初期データに入れる値を文脈に合わせてエスケープする`

## Validation and Acceptance

### 検証の前提

アップグレードの不具合は、「古い版で入れたサイト」を「新しい版」でアップグレードして確かめる。ただし、6 件のうち 5 件は、版の違いではなくインストーラの処理そのものが原因なので、マイルストーンごとの確認は次の「同じ版での入れ直し」で行い、版をまたぐ確認は最後の「通しの確認」で 1 回だけ行う。

- 修正前の環境: `git -C <repo> worktree add --detach <repo>/.claude/worktrees/issue-654-before origin/main` で作る（`<repo>` は `/home/yamamoto/ghq/github.com/modxcms-jp/evolution-jp`）。名前は `EVO_VALIDATE_NAME=evo-validate-654-before EVO_BIND_IP=127.0.0.55`。
- 修正後の環境: `<wt>` を使う。名前は `EVO_VALIDATE_NAME=evo-validate-654 EVO_BIND_IP=127.0.0.54`。
- 起動とインストール: 環境ごとに、`.claude/skills/adaptive-validation-runner/references/environment.md` の「並走するとき」で同名の環境が無いことを確かめてから、`EVO_VALIDATE_NAME=<名前> EVO_BIND_IP=<アドレス> docker compose --project-directory <作業ツリー> -f <作業ツリー>/compose.yml -f <作業ツリー>/.claude/skills/adaptive-validation-runner/scripts/compose.validation.yml up -d --build` で起動し、その作業ツリーをカレントにして `NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/setup-site.js --sample` でインストールする（Playwright の入れ方は同じファイルの「起動からスモークテストまで」）。以下、同梱スクリプトは、この形で実行する。シェルの状態は残らないので、`docker compose` と同梱スクリプトの先頭に毎回同じ名前とアドレスを付ける。
- 同じ版での入れ直し: インストール済みのサイトで、管理画面にログインしたまま `http://<アドレス>/install/index.php?action=mode` を開くと、インストーラはアップグレードとして動く（テーブルがそろっているため）。「次へ」で オプション画面 → 確認画面 → 実行 と進む。この操作は Playwright のスクリプト（作業用ディレクトリ `<work>`（リポジトリの外。例 `/tmp/evo-654`）に置く `<work>/upgrade.js`）で行う。`.claude/skills/adaptive-validation-runner/scripts/lib.js` の `login()` でログインし、画面の進め方は `.claude/skills/adaptive-validation-runner/scripts/setup-site.js` の 85〜105 行を見本にする。オプション画面では、チェックを外す要素の名前を引数で受け取り、各 `input[name="plugin[]"]` のチェックの状態を出力する。
- 状態の用意と読み取り: SQL は `<work>/<名前>.sql` に書き、`docker exec -i <名前>-db mariadb -uuser -ppassword evodb < <work>/<名前>.sql` で流す（引用符を含む SQL をシェルの引数に書かない）。読み取りは `docker exec <名前>-db mariadb -uuser -ppassword evodb -e "<SELECT 文>"`。
- 2 段目（設定画面）の確認: DB の `settings_version` を古い値にし（`UPDATE modx_system_settings SET setting_value='1.2.2J' WHERE setting_name='settings_version'`）、`docker exec -w /var/www/html <名前>-app php evo cache:clear` のあと、管理者で `index.php?a=17` を開く。
- やり直し: インストール直後に `docker exec <名前>-db mariadb-dump -uuser -ppassword evodb > <work>/<名前>-fresh.sql` と `cp <作業ツリー>/manager/includes/config.inc.php <work>/<名前>-config.inc.php` で控え、戻すときは逆に流す（設定ファイルは 0404 なので、先に `chmod u+w` する）。M1 のあとは DB の既定の文字セットも戻す（`ALTER DATABASE evodb CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci`）。

### M1（#654）

用意（両方の環境）: 全テーブルを utf8mb3 にし、設定ファイルの文字セットを `'utf8'` にして、utf8 で入れた古いサイトの状態を作る。

    ALTER DATABASE evodb CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
    （SHOW TABLES LIKE 'modx\_%' の各テーブルに）ALTER TABLE <表> CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
    chmod u+w <作業ツリー>/manager/includes/config.inc.php
    sed -i "s/env('DB_CHARSET', '[^']*')/env('DB_CHARSET', 'utf8')/" <作業ツリー>/manager/includes/config.inc.php

操作: 同じ版での入れ直し（オプション画面の「utf8mb4 に変換する」は既定のまま）。

確かめること:

- `grep DB_CHARSET <作業ツリー>/manager/includes/config.inc.php` が、修正前は `'utf8mb3'`（または `'utf8'`）、修正後は `'utf8mb4'`。
- `docker exec -w /var/www/html <名前>-app php evo db:query "SELECT @@character_set_client AS c"` が、修正前は `utf8mb3`、修正後は `utf8mb4`。
- `php evo db:query "UPDATE modx_site_content SET pagetitle='絵文字😀' WHERE id=1"` のあと、`SELECT HEX(pagetitle) FROM modx_site_content WHERE id=1` が、修正前は `F09F9880` を含まない（`3F` になるか、更新がエラーになる）、修正後は `F09F9880` を含む。
- 回帰: 用意をやり直し、オプション画面で「変換しない」を選ぶと、修正後も設定ファイルの文字セットは utf8mb4 にならない（テーブルも utf8mb3 のまま）。

### M2（#655）

用意（両方の環境）:

    UPDATE modx_user_roles SET name='管理者（独自）', description='独自の説明', empty_trash=0 WHERE id=1;
    UPDATE modx_user_roles SET empty_trash=0 WHERE id=2;

操作と確かめること（読み取りは `SELECT id,name,description,empty_trash FROM modx_user_roles WHERE id IN (1,2)`）:

- 同じ版での入れ直しのあと: 修正前は ID 1 が `Administrator`・初期の説明・`empty_trash=1` に戻る。修正後は `管理者（独自）`・`独自の説明`・`0` のまま。
- 続けて 2 段目（`settings_version='1.2.2J'`）: 修正前は ID 1・2 とも `empty_trash=1` になる。修正後（Q3 が案 i か ii）は 0 のまま。
- 修正後だけ: `settings_version='1.0.19J'` で 2 段目を開くと、ID 2 の `empty_trash` が 1 になる（Q3 が案 i のとき。古いサイトへの配布は残る）。
- 修正後だけ: 列を足したときの付与。`ALTER TABLE modx_user_roles DROP COLUMN view_schedule` のあと同じ版での入れ直しをすると、列が戻り、ID 1 は `view_schedule=1`、ID 3 は 0。
- 修正後だけ: 新規インストール（`.claude/skills/adaptive-validation-runner/scripts/setup-site.js --reinstall`）のあと、`SELECT * FROM modx_user_roles WHERE id=1` で、`id`・`name`・`description` 以外の列がすべて 1。

### M3（#658・#657）

関数（ホスト、修正前と修正後の作業ツリーそれぞれで）:

    php -r 'require "<作業ツリー>/install/functions.php"; var_dump(propUpdate("&tpl=テンプレート;string;@CODE:<a href=\"[+url+]\">[+title+]</a>", "")); var_dump(propUpdate("&a=Show;list;yes,no;yes &b=Show;list;yes,no;yes", "")); var_dump(propUpdate("&a=A;string;x &b=B;string;y", "&a=A;string;changed"));'

修正後は、1 つ目が `&tpl=テンプレート;string;@CODE:<a href="[+url+]">[+title+]</a> `、2 つ目が `&a=Show;list;yes,no;yes &b=Show;list;yes,no;yes `、3 つ目が今と同じ `&a=A;string;changed &b=B;string;y `（利用者の値が残る）。修正前の値は Surprises & Discoveries のとおり。

用意（両方の環境。`<work>/m3-state.sql`）:

    UPDATE modx_site_plugins SET disabled=1 WHERE name='Quick Manager+';
    UPDATE modx_site_plugins SET description=REPLACE(description,'<strong>1.3</strong>','<strong>1.2</strong>') WHERE name='Forgot Manager Login' AND disabled=0;
    UPDATE modx_site_plugins SET description=REPLACE(description,'<strong>0.4</strong>','<strong>0.3</strong>'), properties=CONCAT(properties,' &tpl=テンプレート;string;@CODE:<a href="[+url+]">[+title+]</a> &dupA=Show;list;yes,no;yes &dupB=Show;list;yes,no;yes') WHERE name='ManagerManager' AND disabled=0;

操作: 同じ版での入れ直し。オプション画面で `Forgot Manager Login` のチェックだけを外す。

確かめること（読み取りは `SELECT id,name,disabled FROM modx_site_plugins WHERE name IN ('Quick Manager+','Forgot Manager Login','ManagerManager') ORDER BY name,id` と、`ManagerManager` の有効な行の `properties`）:

- オプション画面の `Quick Manager+`: 修正前はチェック付きで出る。修正後（Q1 案 B）はチェック無しで、文言が添えてある（案 A なら出ない）。
- `Quick Manager+`: 修正前は有効な行が新しく 1 つ増える。修正後は無効な行だけのまま。
- `Forgot Manager Login`: 修正前はチェックを外しても新しい有効な行が入る（古い行は無効になる）。修正後（Q2 案 a か b）は行が変わらない。
- `ManagerManager` の新しい有効な行の `properties`: 修正前は `@CODE:<a href` で切れ、`dupB` が無い。修正後は `@CODE:<a href="[+url+]">[+title+]</a>`・`dupA`・`dupB` がすべて残る。
- 修正後だけ: 新規インストール（`.claude/skills/adaptive-validation-runner/scripts/setup-site.js --reinstall --sample`）で、base のプラグイン 4 つ（`Quick Manager+`・`TinyMCE 7 Rich Text Editor`・`ManagerManager`・`Forgot Manager Login`）がすべて有効な行で入る（Q2 案 a のとき。案 b なら、チェックを外したものは入らない）。

### M4（#659）

用意（両方の環境）: 2 段目の用意（`settings_version='1.2.2J'` とキャッシュの作り直し）をし、root で `SET GLOBAL innodb_force_primary_key=ON; SET GLOBAL general_log_file='/tmp/general.log'; SET GLOBAL general_log=ON;` を流す（`docker exec -i <名前>-db mariadb -uroot -ppassword`）。

確かめること:

- 管理者で `a=17` を開く。修正前は DB のエラー画面で止まる。MariaDB がこの操作を拒まず止まらない場合は、`docker exec <名前>-db grep -c 'DROP PRIMARY KEY' /tmp/general.log` が 2 以上になることで、外して付け直していることを確かめる。修正後は設定画面（`#Button1`）が出て、同じ数が 0。
- 修正後だけ: 主キーが違う・無いときは直す。`innodb_force_primary_key=OFF` にして次の 2 文を流し、`ON` に戻してから 2 段目を開く。

        ALTER TABLE modx_site_tmplvar_templates DROP PRIMARY KEY, ADD PRIMARY KEY (tmplvarid, templateid, `rank`);
        ALTER TABLE modx_site_plugin_events DROP PRIMARY KEY;

  エラーにならず、`SHOW KEYS FROM modx_site_tmplvar_templates WHERE Key_name='PRIMARY'` が `tmplvarid`・`templateid` の 2 行、`site_plugin_events` が `pluginid`・`evtid` の 2 行になる。
- 片付け: `SET GLOBAL innodb_force_primary_key=OFF; SET GLOBAL general_log=OFF;`

### M5（#656）

設定ファイル（ホスト、修正後だけ）: M5 で足した関数に、`install/tpl/config.inc.tpl` の中身と、`"`・`'`・`\`・`$`・`[+dbase+]` を含むユーザー名とパスワードを渡し、結果を `<work>/c656/config.inc.php` に書く。`php -l` が通り、次の確認用スクリプトで読み戻した値が渡した値と一致する。確認用スクリプト `<work>/check-config.php` は、`env()` を「第 2 引数をそのまま返す関数」として定義してから、引数のファイルを一時ディレクトリへ写してから `@include` し（設定ファイルの末尾は同じディレクトリの `initialize.inc.php` を読み込むが、写した先には無いので、本体を起動せずに警告だけで済む）、`$database_user`・`$database_password`・`$database_connection_charset`・`$dbase`・`$table_prefix`・`$https_port`・`$lastInstallTime` を `var_export()` で出す。

修正前の値の変わり方（修正前の環境）: `docker exec <名前>-app php -r '$m = new mysqli("db","user","password","evodb"); echo $m->real_escape_string("ab\"c"), PHP_EOL;'` が `ab\"c` を出す。単引用符の文字列の中では `\"` がそのまま残るので、読み込まれる値は `ab\"c` になる。

新規インストール（両方の環境）: `EVO_ADMIN_USER="o'brien" EVO_ADMIN_EMAIL="o'brien@example.com"` を先頭に付けて `.claude/skills/adaptive-validation-runner/scripts/setup-site.js --reinstall` を実行する。

- 修正前はインストールが失敗する（SQL のエラーが出て、作ったテーブルが片付けられる）。
- 修正後は成功し、`o'brien` でログインできる。`SELECT username FROM modx_manager_users WHERE id=1` が `o'brien`、`SELECT fullname,email FROM modx_user_attributes WHERE internalKey=1` が `o'brien`・`o'brien@example.com`、`emailsender` の設定が `o'brien@example.com`。
- 修正後は、書き出された `<wt>/manager/includes/config.inc.php` に `php -l` が通り、`php <work>/check-config.php <wt>/manager/includes/config.inc.php` の値が入力どおり（`user`・`password`・`evodb`・`modx_`・`utf8mb4`・`'443'`・数値）。
- `.claude/skills/adaptive-validation-runner/scripts/setup-site.js` の後に `.claude/skills/adaptive-validation-runner/scripts/smoke.js` が全 PASS する。

### 通しの確認（M5 のあと、PR を下書きから戻す前に 1 回）

1.2.2J（タグ `release-1.2.2J`）で入れたサイトを、修正前（origin/main）と修正後（ブランチの先頭）でアップグレードし、6 件をまとめて確かめる。

1. `git -C <repo> worktree add --detach <repo>/.claude/worktrees/issue-654-e2e-before release-1.2.2J` と、同じく `issue-654-e2e-after` を作る。1.2.2J にはこのスキルが無いので、`.claude/skills/adaptive-validation-runner/references/environment.md` の「1.2 系（保守ブランチ・リリースタグ）で検証するとき」に従い、`<work>/scripts/` に main のスクリプトを取り出して使う。名前とアドレスは `evo-validate-654-e2e-before`（127.0.0.56）と `evo-validate-654-e2e-after`（127.0.0.57）。
2. それぞれ `<work>/scripts/setup-site.js --sample --editor` で 1.2.2J を入れ、M1・M2・M3 の用意を流す（M3 の版の書き換えは、1.2.2J と新しい版でプラグインの版が同じ場合に要る）。`mariadb-dump` と設定ファイルの写しで控える。
3. コードを切り替える。`git -C <e2e-before> checkout --detach origin/main`、`git -C <e2e-after> checkout --detach fix/654-upgrade-data-safety`（ブランチの先頭の commit。未コミットの変更は入らない）。OPcache のため数秒待つ。
4. root で `SET GLOBAL innodb_force_primary_key=ON` にしてから、`<work>/upgrade.js` でアップグレードする（`Forgot Manager Login` のチェックを外す）。続けて管理者で `a=17` を開き、保存する。
5. M1〜M4 の「確かめること」をまとめて読み取り、表（条件 × 修正前 × 修正後）にする。修正後で、設定ファイルが `'utf8mb4'`、ロール ID 1 と 2 の手直しが残る、`Quick Manager+` が無効のまま、`Forgot Manager Login` が変わらない、`ManagerManager` のプロパティが残る、設定画面が開けて保存でき `settings_version` が今の版になる、こと。
6. 片付け: `innodb_force_primary_key=OFF`、各環境を `down -v --rmi local`、検証で作られた `config.inc.php` を消し、`git -C <repo> worktree remove` で before・e2e の作業ツリーを消す。

受け入れの条件: M1〜M5 と通しの確認で、修正後の列がすべて期待どおりで、修正前の列で各 Issue の症状が再現していること。#654・#655・#657・#658・#659・#656 が PR のマージで閉じること。

## Idempotence and Recovery

`install/sql/fix_settings.php` は新規インストールとアップグレードのたびに条件なしで動くので、M2 の変更は何度実行しても同じ結果になるように書く（行が無いときだけ入れる、列を足したときだけ付ける）。M4 の主キーの処理も、目的の形なら何もしないので、何度開いても同じ。M1 の変換は、変換済みのテーブルを対象にしない（`getTablesToConvert()`）。

中断したときは、Progress の最初の未完了のマイルストーンから再開する。作業ツリー `<wt>` の未コミットの変更は `git -C <wt> status --short` で確かめ、`git stash` は使わない。検証環境は、控えた `<名前>-fresh.sql` と設定ファイルの写しで戻すか、`.claude/skills/adaptive-validation-runner/scripts/setup-site.js --reinstall` で入れ直す。`innodb_force_primary_key` と `general_log` は DB を作り直すと既定（OFF）に戻る。別のセッションの検証環境は止めない・消さない。

## Artifacts and Notes

- ブランチ: `fix/654-upgrade-data-safety`（main ceeff2009 から作成、#654 に紐づけ済み）
- 作業ツリー: `.claude/worktrees/issue-654`
- PR: 未作成
- Issue（着手順）: #654, #655, #657, #658, #659, #656（#656 は `security` ラベル。PR 本文・コミットには、値の扱いの説明だけを書き、悪用の手順を書かない）
- ロードマップ: `.agent/roadmap.md` の「アップグレードで利用者のデータや設定を壊す不具合を直す」（`ExecPlan:` の反映はメインのセッションが行う）
- 想定コミット: Concrete Steps の各マイルストーンのコミット案（M3 は 2 つ）。PR は 1 つで、本文に `Closes #654` など 6 件を並べ、「動きが変わる点」を書く。
- 検証の手順の正本: `.claude/skills/adaptive-validation-runner/references/environment.md`

## Interfaces and Dependencies

- 外部依存は増やさない。PHP の標準関数（`var_export()`・`strtr()`）だけを使う。
- インストーラの関数（`install/functions.php`）は、インストーラの中からだけ呼ばれる。M5 で足す関数も、`install/instprocessor.php` からだけ呼ぶ。
- `install/tpl/config.inc.tpl` の書式（タグを囲む引用符を外す）は、`install/instprocessor.php` だけが使う。書き出された `config.inc.php` の見た目（`env('DB_CHARSET', 'utf8mb4')` など）は変わらないので、`convert2utf8mb4` の置き換えや、既存のサイトの設定ファイルとの互換は保たれる。
- `manager/includes/upgrades/upd_db_structure.php` と `manager/includes/upgrades/upgrades.php` は、設定画面（`manager/actions/tool/mutate_settings.dynamic.php`）からだけ読み込まれる。
- インストーラの作り直し（#485）は、この作業を先に片付けてから着手する（ロードマップの依存関係）。この作業は、#485 とマイグレーション機構（#667）を待たない（ロードマップのメモ/判断ログ）。
- `security` ラベルの #656 を含むので、push の前に exec-plan-runner の事前確認（非公開アドバイザリとの重なりを番号で確かめる）を行う。
