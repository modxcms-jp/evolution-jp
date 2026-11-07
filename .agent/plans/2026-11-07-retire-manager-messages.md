# ExecPlan: 管理画面のメッセージ機能を廃止する（Issue #1069）

## Purpose / Big Picture

管理ユーザーどうしでメッセージを送り合う機能（管理画面上部の「メッセージ (n / n)」、`index.php?a=10`・`a=65`・`a=66`）を、画面・処理・権限・設定・言語・テーマ・DB からまとめて取り除く。使われていない機能の手入れのコストと、権限を持つ管理ユーザーのブラウザが 60 秒ごとに出す件数確認の POST が無くなる。

## Progress

- [x] (2026-11-07) M1: 画面と処理を外した（17 ファイル。grep の残りは M2 の 2 件だけ、`php -l` は PHP 7.4 で通過）
- [x] (2026-11-07) M2: 権限・設定・言語・テーマを外した（言語キーは英語・日本語とも 34 個、画像 5 点、7 テーマの CSS、theme-spacing.md）
- [x] (2026-11-07) M3: DB を変えた（マイグレーション `20261107_112600_retire_manager_messages`。初期データの SQL は 5 行とも列 72・値 72 で、`messages` 以外の列と値の対応は変更前と同じ）
- [ ] M4: Docker での検証（新規インストール、メッセージのある DB からのアップグレード、画面）

## Surprises & Discoveries

- 2026-11-07: Issue に挙がっていない、メッセージ機能のためだけの箇所が見つかった。`manager/index.php` の `postv('updateMsgCount')` の参照は冒頭の処理のほかに 3 か所あり、既定の action の判定、旧 frameset の入口、ダッシュボードの canonical 転送の条件に入っている。ほかに、`manager/includes/csrf_token.php` の 65・66（「メッセージ」の見出し）、`manager/frames/menu.php` の `window.mainMenu.updateMail`（旧フレームとの互換 API）、シェルの部分再描画が呼ぶ `initMenuState()` の引数、エラーコード 13・14（`manager/includes/error.class.inc.php`。宛先の未選択）、`pm2email` 設定（メッセージをメールにも送る）、`topmenu_user` の既定値の `messages`、画像 `forward.gif`・`reply.gif`・`new1-09.gif`、全テーマの CSS の `.messageRead`・`.messageUnread`・`.message-quote`・`#supplementalNav .supplementalNav__item--messages`。
- 2026-11-07: `install/sql/default_settings.sql` と `install/sql/default_settings_custom.sql` の `user_roles` の INSERT は、列の並びの位置で値を入れている。列名の `messages`（16 番目）を消すときは、各行の 16 番目の値も消す必要がある。
- 2026-11-07: `[+iconMessage+]` を置いている同梱の welcome.tpl は 4 つ（common・RevoStyle・AromaHop・SakuraFlow）。ほかのテーマは common の welcome.tpl を使う。`[+MessageInfo+]`・`[+MessageIcon+]` は同梱のテンプレートに無く、外部テーマのためだけに出している。`welcome.static.php` は最後に、置き換わらなかった `[+…+]` を消すので、外部テーマに残っても空になる。
- 2026-11-07: `manager/frames/menu.php` の `initMenuState()` は、keepMeAlive（60 秒ごと）とメッセージの件数確認（初回の 1 回と `mail_check_timeperiod` 秒ごと）を一緒に登録している。件数確認だけを外す。
- 2026-11-07: `buildMenu()`（`manager/frames/menu.php`）は、`topmenu_*` の値にあって `$item` に無いキーを飛ばす。既存サイトの `topmenu_user` に `messages` が残っていても、エラーにはならない。
- 2026-11-07: `getAction()`（`manager/includes/actionlist.inc.php`）は、一覧に無い番号を `Idle (unknown)` と表示する。`active_users` に action 10・65・66 の行が残っていても、エラーにはならない。

## Decision Log

- 2026-11-07 / yamamoto・Claude: Issue に挙がっていない上の箇所も、メッセージ機能のためだけにあるので、今回まとめて外す。`pm2email` は送信処理（`send_message.processor.php`）だけが読む設定で、送信が無くなれば意味が無い。エラーコード 13・14 も送信処理だけが使う。代替案の「Issue の列挙どおりにして残りは別 Issue」は、使われない設定・コード・CSS が残り、後で同じ棚卸しをやり直すことになるので採らない。
- 2026-11-07 / yamamoto・Claude: 既存サイトの `topmenu_user` は、旧既定値 `change_user_pf,change_password,messages` のままのときだけ、マイグレーションで `change_user_pf,change_password` に直す。利用者が並べ替えた値は書き換えない（`assets/docs/migrations.md` の「利用者の設定を上書きしない」）。残った `messages` は `buildMenu()` が飛ばすので、害は無い。
- 2026-11-07 / yamamoto・Claude: `00000000_baseline.php` は変えない。baseline は Administrator ロールが無いときに `messages` 列つきで入れ直すが、baseline は名前の順で新しいマイグレーションより先に流れ、その時点の古い DB には `messages` 列がある。新規インストールの DB はマイグレーションを流さず、すべてを適用済みとして記録する。baseline の見出しの決まり（`create_tables.sql` を変えたら、baseline を書き換えず同じ変更のマイグレーションを足す）にも合う。
- 2026-11-07 / yamamoto・Claude: PR は 1 つにまとめ、マイルストーンごとにコミットを積む。どのマイルストーンも単独で出すと、消した画面を指すリンクや、使わない列・設定が一時的に残るため。

## Outcomes & Retrospective

## Context and Orientation

用語:

- **action**: 管理画面の画面や処理の番号。`manager/index.php?a=<番号>` の `switch` で振り分ける。10 がメッセージ画面、65 が削除、66 が送信。
- **権限**: ロール（`user_roles` テーブルの 1 行）が持つ 0・1 の列。`evo()->hasPermission('messages')` は、ログイン中のユーザーのロールの `messages` 列を見る。
- **グローバル設定**: `system_settings` テーブルの名前と値の組。画面は「ツール > グローバル設定」（`manager/actions/tool/mutate_settings/`）。保存処理は、送られた値に `manager/includes/default.config.php` の既定値を足して保存する（`manager/processors/save_settings.processor.php`）。既定値に残すと、画面から消しても保存のたびに書き戻される。
- **マイグレーション**: 既存サイトの DB を新しい形にする PHP ファイル（`manager/includes/migrations/`）。書き方と決まりは `assets/docs/migrations.md` を正とする。
- **シェル**: 管理画面を 1 枚のページで画面だけ入れ替える仕組み。メニュー（`manager/frames/menu.php`）は部分的に再描画され、そのとき `#topMenu` の中の script だけが実行される。

対象（main の 2026-11-07 時点。行は目安）:

画面と処理（M1）

- `manager/index.php`: 冒頭の `updateMsgCount` の処理（`getMessageCount()` を呼んで件数を返す）、`postv('updateMsgCount') === null` を含む 3 つの条件、`case 10`・`65`・`66`
- `manager/includes/actionlist.inc.php`: `'10'`・`'65'`・`'66'`
- `manager/includes/csrf_token.php`: `// メッセージ` の見出しと 65・66
- `manager/actions/permission/messages.static.php`、`manager/processors/pm/send_message.processor.php`、`manager/processors/pm/delete_message.processor.php`（ファイルごと消す。`processors/pm/` は空になれば消す）
- `manager/includes/extenders/ex_managerapi.php`: `getMessageCount()`、`getMgrPermissions()` の「管理ユーザーが 1 人なら `messages` を 0 にする」if
- `manager/frames/menu.php`: `var msgcheck`、`initMenuState()` の件数確認（keepMeAlive の登録は残す）、`updateMail()`・`showResponse()`、`window.mainMenu` の `updateMail`、`Evo.ready` とシェル再描画の `initMenuState(...)` の引数、上部の `#newMail`・`#msgCounter`・`.supplementalNav__item--messages` の出力、ユーザーメニューの `$item['messages']`
- `manager/actions/main/welcome.static.php`: `MessageInfo`・`MessageIcon` を作る if（セッションの `nrnewmessages`・`nrtotalmessages` の読み書きもここ）
- `manager/media/style/common/welcome.php`: `iconMessage()`
- `manager/media/style/common/style.defaults.php`: 既定値の `'iconMessage' => 1` と `iconMessage()` の呼び出し
- `manager/media/style/common/config.php.sample`: `'iconMessage' => 1`
- welcome.tpl の `[+iconMessage+]`: `manager/media/style/{common,RevoStyle,AromaHop,SakuraFlow}/welcome.tpl`
- `manager/includes/error.class.inc.php`: 13・14

権限・設定・言語・テーマ（M2）

- `manager/actions/permission/mutate_role.dynamic.php`: `render_form('messages', ...)`
- `manager/processors/permission/save_role.processor.php`: 権限名の列挙の `messages`
- `manager/actions/tool/mutate_settings/tab4_manager_settings.inc.php`: `mail_check_timeperiod`・`number_of_messages`・`pm2email` の行
- `manager/includes/default.config.php`: `mail_check_timeperiod`・`number_of_messages`・`pm2email` を消し、`topmenu_user` を `change_user_pf,change_password` にする
- `manager/includes/lang/english.inc.php`・`japanese-utf8.inc.php`（言語ファイルはこの 2 つだけ）: 消すキーの候補は `messages`・`messages_*`（22 個）・`inbox`・`you_got_mail`・`welcome_messages`・`role_messages`・`mail_check_timeperiod_title`・`mail_check_timeperiod_message`・`nomessages_title`・`nomessages_message`・`error_no_recipient_user`・`error_no_recipient_group`・`pm2email_title`・`pm2email_message`
- `manager/media/style/common/style.defaults.php`: `icons_mail`・`icons_message_forward`・`icons_message_reply`・`icons_mail_large`・`icons_mail_new_large`（`icons_email` は残す）
- 画像: `manager/media/style/common/images/icons/` の `forward.gif`・`reply.gif`・`new1-09.gif`・`32x/mail.png`・`32x/mail_new.png`（`email.png` は `icons_email` が使うので残す）
- 7 テーマの `style.css`（RevoStyle・AuroraFlow・SoftSlate・AromaHop・SakuraFlow・RubberWhite・RevoClassic）: `.messageRead`・`.messageUnread`・`#supplementalNav .supplementalNav__item--messages`・`#newMail`・`#msgCounter`（`a`・`img` の子孫セレクタを含む）・`.inbox-title`・`.message-count-new`・`.message-quote`・`ul.actionButtons.messages-compose-actions`
- `assets/docs/theme-spacing.md`: `.messages-compose-actions` の記述 4 か所（例外の一覧、class の説明、#924 の一覧、新しいテーマの手順の写す規則）

DB（M3）

- `install/sql/create_tables.sql`: `user_messages` の CREATE TABLE、`user_roles` の `messages` 列
- `install/sql/default_settings.sql`・`install/sql/default_settings_custom.sql`: `user_roles` の INSERT・REPLACE の列名 `messages` と、各行の 16 番目の値
- `install/convert2utf8mb4.php`: 変換対象の一覧の `'user_messages'`
- 新しいマイグレーション `manager/includes/migrations/<YYYYMMDD_HHMMSS>_retire_manager_messages.php`

## Plan of Work

M1 で画面と処理を外す。入口（action・メニュー・ダッシュボード）とその先の処理を同じコミットで消し、消した画面を指すリンクが残らないようにする。`menu.php` は keepMeAlive を残し、`initMenuState()` の引数からメッセージ関係を外す（引数が無くなれば `initMenuState()` にする。呼び出しは同じファイルの 2 か所だけ）。

M2 で、画面から参照されなくなった権限・設定・言語・テーマの部品を消す。言語キーは、キーごとに `grep -rn "<キー>"` で `manager/`・`assets/`・`install/` に参照が無いことを確かめてから消す（`$_lang['key']`・`$_lang["key"]`・`lang('key')`・`[%key%]` の形がある）。CSS は 7 テーマの該当の規則をまるごと消す。複数のセレクタをまとめた規則（例: RevoStyle の `#msgCounter, #newMail`）は、ほかのセレクタが残るならそれだけを外す。RevoStyle は凍結テーマだが、使われない規則を外すだけなので対象に含める。

M3 で DB を変える。新規インストールは `create_tables.sql` と初期データの SQL から外す。既存サイトはマイグレーション 1 本で、`dropTableIfExists('user_messages')`、`dropColumnIfExists('user_roles', 'messages')`、`deleteSetting()` で `mail_check_timeperiod`・`number_of_messages`・`pm2email` を消し、`topmenu_user` が旧既定値のときだけ直す。補助は `manager/includes/migration_helper.class.inc.php` の既存メソッドだけで足りる。各処理は変えたときだけ `$m->log()` を残す（baseline の 9 番の書き方にそろえる）。

M4 で、新規インストールとアップグレードの両方を Docker で確かめる。

## Concrete Steps

M1（コミット例: `feat(manager): メッセージ機能の画面と処理を外す`）

1. 上の「画面と処理（M1）」のファイルを編集・削除する。
2. 実行: `grep -rnI --exclude-dir=tinymce -e updateMsgCount -e updateMail -e msgcheck -e getMessageCount -e nrnewmessages -e nrtotalmessages -e iconMessage -e MessageInfo -e MessageIcon -e "a=10\b" -e "a=65\b" -e "a=66\b" manager assets install`
   期待: 出力が無い（`.agent/` の過去の記録は対象外）。
3. 実行: 変えた PHP ファイルごとに `docker compose exec <app> php -l <ファイル>`。期待: `No syntax errors detected`。

M2（コミット例: `feat(manager): メッセージの権限・設定・言語・テーマの部品を外す`）

1. 上の「権限・設定・言語・テーマ（M2）」のファイルを編集し、画像を `git rm` する。
2. 実行: `grep -rnI --exclude-dir=tinymce -e "'messages'" -e "role_messages" -e "you_got_mail" -e "mail_check_timeperiod" -e "number_of_messages" -e "pm2email" -e "icons_mail" -e "icons_message_" -e "messages-compose" -e "messageRead" -e "messageUnread" -e "message-quote" -e "message-count-new" -e "inbox-title" -e "newMail" -e "msgCounter" -e "mail_new" -e "32x/mail" -e "new1-09" -e "forward.gif" -e "reply.gif" manager assets install`
   期待: 出力が無い。例外は、M3 で直す `install/`・`00000000_baseline.php`、`site_export_job` などの別の意味の `'messages'`。
3. 言語ファイルを `php -l` し、英語と日本語で消したキーの数が同じことを `grep -c` で確かめる。

M3（コミット例: `feat(install): メッセージのテーブル・列・設定をマイグレーションで消す`）

1. `docker compose exec <app> php evo make:migration retire_manager_messages` で雛形を作り、上の処理を書く。
2. `create_tables.sql`・`default_settings.sql`・`default_settings_custom.sql`・`convert2utf8mb4.php` を編集する。初期データの SQL は、編集後に各 INSERT・REPLACE の列の数と各行の値の数が一致することを数えて確かめる（例: 一時的に PHP で列名と値を `,` で数える。結果は Artifacts and Notes に残す）。
3. 期待: `php evo migrate:status` に新しいマイグレーションが未適用で出る。

M4（検証。`adaptive-validation-runner` を使う。手順と結果は Validation and Acceptance の項目に対応させる）

## Validation and Acceptance

1. 新規インストール直後の DB に `user_messages` テーブルと `user_roles.messages` 列が無く、`system_settings` に `mail_check_timeperiod`・`number_of_messages`・`pm2email` が無い。3 つのロール（Administrator・Editor・Publisher）と custom の 2 つのロールの権限が、main で新規インストールしたものと、`messages` 以外の列で同じ値になる。
2. main で新規インストールし、`user_messages` に行を入れ、`topmenu_user` を旧既定値にした DB を、このブランチのアップデーターで上げると、テーブル・列・3 つの設定が消え、`topmenu_user` が `change_user_pf,change_password` になる。`topmenu_user` を並べ替えた DB では値が変わらない。もう一度 `php evo migrate` を流しても何も変わらず、エラーにならない。
3. 管理画面の上部・ユーザーメニュー・ダッシュボードに、メッセージの表示とアイコンが出ない（テーマは既定と、welcome.tpl を持つ RevoStyle・AromaHop・SakuraFlow のうち 1 つ以上）。ブラウザの開発ツールで、`updateMsgCount` の POST が出ず、`session_keepalive.php` が 60 秒ごとに出る。シェルで画面を行き来したあとも同じ。
4. `index.php?a=10`、`a=65`、`a=66` を直接開くと、ほかの存在しない action（例: `a=9999`）と同じ応答になり、PHP の警告がログに出ない。
5. ロールの編集画面に「メッセージの表示および送信」が無く、ロールを保存できる。グローバル設定に「メッセージチェックの間隔」「メッセージ数」と、メッセージをメールにも送る設定が無く、保存できる。保存後も `system_settings` に 3 つの設定が戻らない。
6. システムログ（`temp/logs/system/`）に、検証中の操作による PHP の警告・エラーが無い。

## Idempotence and Recovery

マイグレーションは、テーブル・列・設定の有無を確かめてから変えるので、何度流しても 2 回目以降は何もしない。途中で止まったら、このブランチのワークツリー（`.claude/worktrees/issue-1069`）で `git log` を見て、Progress の最初の未完了のマイルストーンから続ける。検証環境は `docker compose ... down -v --rmi local` で消して立て直せる。

## Artifacts and Notes

- Issue: #1069
- ブランチ: `feat/1069-retire-manager-messages`（`gh issue develop` で Issue に紐づけ済み）
- ワークツリー: `.claude/worktrees/issue-1069`
- PR: #1131（下書き、`Refs #1069`。最後のマイルストーンで `Closes #1069` に改める）
- 想定のコミット: M1・M2・M3 で 1 つずつ。ExecPlan とロードマップの更新は別のコミット
- リリース記事に書くこと（`仕様変更`）: メッセージ機能の廃止、アップグレードで `user_messages` のデータが消えるのでバックアップを案内、外部のアドオンの `hasPermission('messages')` は常に偽、外部テーマの `[+MessageInfo+]`・`[+MessageIcon+]`・`[+iconMessage+]` は空になる

## Interfaces and Dependencies

- 外部から見える変化: `hasPermission('messages')` が常に偽になる。プレースホルダ `MessageInfo`・`MessageIcon`・`iconMessage` が出なくなる。グローバル関数 `encrypt()`・`decrypt()`（送信処理と画面の中だけで定義）が無くなる。`$_style` の `icons_mail` などのキーが無くなる。`window.mainMenu.updateMail` が無くなる（同梱の JS からの呼び出しは、消す `messages.static.php` だけ）
- 依存: マイグレーションのランナー（`manager/includes/migrator.class.inc.php`）と補助（`manager/includes/migration_helper.class.inc.php`）。新しい補助は足さない
- DocumentParser への影響: 無い。管理画面とインストーラだけの変更で、`executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()` は通らない
- キャッシュへの影響: 設定のキャッシュ（`system_settings` の写し）は、アップデーターが最後に消す。グローバル設定の保存でも作り直される
