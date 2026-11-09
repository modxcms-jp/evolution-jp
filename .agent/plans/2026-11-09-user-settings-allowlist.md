# ExecPlan: 管理ユーザーの設定の保存を、保存してよい設定名の一覧で受ける（Issue #1304）

## Purpose / Big Picture

管理ユーザーの編集画面（新規 `a=11`・編集 `a=12`）から保存したとき、ユーザー設定（ユーザーごとにグローバル設定を上書きする値。表 `user_settings`）に入るのを「画面に出している設定」だけにする。今は POST の項目のうち除外の一覧に無いものをすべて保存しているため、フォームに項目を足すたびに除外の一覧の手当てが要り、漏れると設定ではない値（過去には `save_action`・`csrf_token`）がユーザー設定に入り、そのユーザーの `evo()->config()` を上書きしていた。保存してよい名前を、画面を描くのと同じ定義（`EvoSettingsForm`）から得るようにし、画面と保存の約束を 1 か所にまとめる。

## Progress

- [x] (2026-11-09) M0: 着手前の確認と修正前の記録（main の動きを TSV に残す）（基準 0c8f41e18。a=11・a=12・a=74 が送る項目に、除外の一覧にも設定にも入らない名前は無く、手順 9 のマイグレーションは不要。S1〜S18 をすべて記録し、計画の表の期待値と一致）
- [x] (2026-11-09) M1: 名前の取り出しを `EvoSettingsForm` に集め、画面がプラグインの欄の名前を覚える（保存の結果は変えない）（2e86e43f0、自己レビューの低の手当て 2c51a7816。M0 の記録と、ユーザー設定の行・フォームの欄・送られる項目名の 3 組とも完全一致。グローバル設定の保存はエラー 0。セッションに覚える名前は TinyMCE 7 の 3 つだけ。自己レビューは高・中なし）
- [x] (2026-11-09) M2: `saveUserSettings()` を保存してよい名前の一覧で受け、除外の一覧をなくす（ecc86e0f0。M0 の記録との違いは計画どおりの S8・S10・S15 だけ。フォームの欄と送られる項目名は M0 と同じ。M1 で足した確認も同じ結果。エラー 0。自己レビューは高・中なし。低はリリースノートの文案の直し・ドキュメントへの注意の追加・openedArray の手当てとして M3 に入れた）
- [x] (2026-11-09) M3: ドキュメントと最終確認（PHP 7.4 を含む）。`tab_settings.php` の 4 つの設定を定義に移す課題を `assets/docs/core-issues.md` に書く（c70d2d3bc・1b0300d9b、PR 全体の自己レビューの低の手当てを別コミット。PHP 8.4 で M2 の記録と完全一致、追加の確認 3 点（セッションに名前が無いときの a=12 の保存、引用符・空文字の値の往復、0 件の保存での openedArray）も PASS。PHP 7.4.33 の公式イメージでも S2〜S8・S12〜S14 が PHP 8.4 と同じ行。エラー 0。PR 全体の自己レビューは高・中なし）

## Surprises & Discoveries

M1 で分かったこと（2026-11-09）:

- M1 で保存の結果が変わる経路が 1 つだけある。修正前はプロフィールが覚える名前に `theme_refresher` と `default_manager_inline_style` が混じっていたので、DB にこの 2 つの名前の行があると、プロフィールの保存で消えていた。M1 からは覚える名前に入らないので、保存済みの値のまま残る（検証で再現）。この行がある環境はまず無く、M2 の「一覧に無い行は残す」と同じ向きなので、直さない。
- セッションのキーを `pluginSettingNames.<scope>` に変えたので、更新の前に開いたプロフィールから更新の後に保存すると、その 1 回だけ TinyMCE 7 などのプラグインの欄が保存されない（既存の行は残る。検証で再現）。Decision Log どおりで、出始めるのは M2 ではなく M1 から。
- M1 の時点で、`assets/docs/global-settings.md:53` の「保存の処理の `$defaults` にも同じキーが要る」は当てはまらなくなった。`tab_settings.php:2` と `mutate_user/tpl/form.php:57` のコメントは M2 の後の動きを書いている。どれも M3 の手順 12 でそろえる。

計画時（2026-11-09、main c5cdf5a00 で調査。#1300 のブランチは `save_user.functions.php` などユーザーのファイルに触れていない）に分かったこと:

- 画面の設定のタブは、すでにグローバル設定と同じ定義から描いている。`manager/actions/permission/mutate_user/tpl/form.php:4-9` と `mutate_user_pf.dynamic.php:70-75` が `new EvoSettingsForm(EvoSettingsForm::definition(), …, 'user' | 'profile')` を作り、`render()` を出す。定義の行の `scope`（`manager/includes/settings_form.definition.inc.php`）に `user` / `profile` があるものだけが出る。`EvoSettingsForm::names($scope)`（`settings_form.class.inc.php:149-170`）はその名前の一覧を返し、プロフィールの保存はこれを許可リストに使っている（`save_user.functions.php:67-72` の `profileSettingNames()`）。
- 定義から描く `user` の行は 13 個: `manager_theme`・`manager_language`・`manager_inline_style`・`manager_login_startup`・`which_editor`・`editor_css_path`・`rb_base_dir`・`rb_base_url`・`filemanager_path`・`upload_files`・`upload_images`・`upload_media`・`upload_maxsize`。`profile` はこのうち先頭 6 個。
- 定義の外に、ユーザー編集画面だけが手書きで出す設定が 4 つある。`mutate_user/tpl/form/tab_settings.php` の `allow_manager_access`・`allowed_parents`・`allowed_ip`・`allowed_days[]`。同じ表に、設定ではない `a=12` だけの行（ログイン回数、`failedlogincount`・`blockedcheck`・`blocked`・`blockedafter`・`blockeduntil`。これらは `user_attributes` の列）が挟まっている。
- `saveUserSettings()` の `$defaults`（空で保存してよい設定。`save_user.functions.php:148-154`）の 4 つは、定義の `'userDefault' => true` の行（「システム設定に従う」のチェックボックス `default_<名前>` を付ける行）とちょうど同じ。`assets/docs/global-settings.md:53` は「保存の `$defaults` にも同じキーが要る」と、2 か所を手で合わせる決まりにしている。
- プラグインの欄: `OnInterfaceSettingsRender`（エディターのタブ。`eventScope` が `['system', 'user', 'profile']`）と `OnUserFormRender` の出力はフォームの中に出る。TinyMCE 7 はユーザーの画面で `tinymce7_toolbar_preset`・`tinymce7_menubar`・`tinymce7_entermode` を出す（`assets/plugins/tinymce7/src/TinyMCE7/Settings/SystemSettingsRenderer.php`）。`OnUserFormPrerender` は、プロフィールではフォームの中（`mutate_user_pf.dynamic.php:131-136`）、ユーザー編集画面ではフォームの外（`mutate_user.dynamic.php:73-78`、`<form>` を出す `form.php` より前）に出るので、ユーザー編集画面では送られない。
- プロフィールは、プラグインの欄の名前を表示のときにセッション `profileSettingNames` に覚え（`mutate_user_pf.dynamic.php:131-140, 319-340`、a38b69f3c）、保存でそれを足す。覚える名前は「設定のタブの HTML の全部の欄 − `names('profile')`」で取っているため、本体が出す `theme_refresher`（`manager_theme` の行の `userDesc` の hidden）と `default_manager_inline_style` もプラグインの名前として入っている。今は除外の一覧と `$defaults` の処理で落ちるので害が無いが、除外の一覧をなくすとユーザー設定に入ってしまう。M1 でプラグインの出力だけから取る形に直す。
- ユーザー編集画面の保存（`saveUserSettings()` のプロフィール以外）は、そのユーザーの行をすべて消してから、送られた値だけを入れ直す（`save_user.functions.php:241-258`）。画面が送らない行は保存のたびに消える。`token_auth()`（`manager/includes/document.parser.class.inc.php:1198-1225`）が読む `auth_token` の行も、管理者がそのユーザーを `a=12` で保存すると消える。プロフィールの保存は、画面に出していない行を残す（`save_user.functions.php:199-208`）。
- `user_settings` の行は、名前を問わずそのユーザーの `config` を上書きする（`DocumentParser::getUserConfig()`、`document.parser.class.inc.php:1411-1437` を `getSettings()` の 1347-1350 行で重ねる）。除外の一覧から漏れた名前は、読む側が無くても設定として効く。
- main（#1277 の後）の除外の一覧には `after_save` が入り、旧名の `stay`・`save_action` は外れた。#1277 の ExecPlan の Interfaces に書いたとおり、更新の前に開いていた画面から保存すると `save_action` の行ができる。許可リストにすれば、この行もできなくなる。
- 今の画面が送る項目は、手で数えた限り、すべて除外の一覧か設定のどちらかに入っている（`tab_general.php`・`tab_profile.php`・`tab_access.php`・`tab_settings.php`・`form.php`、`aButtonSave()` の `after_save`、CSRF の `csrf_token`、JS の `changed_settings`、定義の行の `theme_refresher`・`default_*`）。過去に漏れた名前（`csrf_token`・`save_action`・`blockedcheck`・`tinymce7_template_*`）は、`manager/includes/migrations/00000000_baseline.php:355-363` が消している。M0 で、実際に送られる項目を画面から取って確かめる。
- 1.0.4J と 1.2.4J のユーザー編集画面の設定の欄は、今と同じ組（1.0.x だけ `upload_flash` / `default_upload_flash` がある。読む側は無い）。古い版の画面にだけあった設定の行が残っていても、効くものは無い。
- Web ユーザーの保存（`save_web_user.processor.php:490-514`）は、`login_home`・`allowed_ip`・`allowed_days` の 3 つの許可リストで、表も画面も別。この Issue では変えない。

## Decision Log

- 2026-11-09 / Claude（planner）: 保存してよい名前の正は `EvoSettingsForm`（`manager/includes/settings_form.class.inc.php`）に置く。中身は 4 つで、どれも画面と保存が同じものを読む。
  1. 定義から描く行: 定義の `scope`（今の `names($scope)`）。画面は `render()` が、保存は `names()` が同じ行を読む。
  2. 「システム設定に従う」が付く行（空で保存してよい設定）: 定義の `userDefault`。新しい `userDefaultNames($scope)` で読み、`saveUserSettings()` の `$defaults` の手書きをやめる。
  3. ユーザー編集画面が手書きで出す 4 つ: クラスの定数 `USER_TAB_KEYS`（`allow_manager_access`・`allowed_parents`・`allowed_ip`・`allowed_days`）にし、`names('user')` に含める。`tab_settings.php` の先頭に、欄を足したらこの定数にも足すことをコメントで書く。
  4. プラグインの欄: 画面を描いたときに、プラグインのイベントの出力（`OnInterfaceSettingsRender` は `render()` の中で、`OnUserFormRender`・プロフィールの `OnUserFormPrerender` は画面で）から欄の名前を取り、セッションに覚える。取り出しの関数 `fieldNames()` は `mutate_user_pf.dynamic.php` から `EvoSettingsForm::fieldNames()` に移し、両方の画面で使う。
  理由: 画面の設定のタブはすでにこの定義から描いており、プロフィールの保存も `names('profile')` を許可リストにしている。新しい仕組みを足さずに、ユーザー編集画面の保存を同じ形にそろえられる。`$defaults` も定義から読むので、`global-settings.md:53` の「2 か所を手で合わせる」決まりがなくなる。
  不採用: (a) `save_user.functions.php` に許可リストの配列を書く案は、定義と同じ名前を 2 か所に持つことになり、今の `$defaults` と同じ「手で合わせる」形になるので採らない。(b) `tab_settings.php` の 4 つも定義の行にする案は、`allowed_days` の複数のチェックボックスの型が `EvoSettingsForm` に無く、設定ではない `a=12` だけの行（ブロックなど）が同じ表に挟まっていて、画面の組み替えになるので、この Issue では採らない（定数とコメントで 1 か所にする。後で定義に移すなら別 Issue）。(c) 保存のときにプラグインのイベントを呼び直して名前を取る案は、画面の番号で出し分けるプラグインがあるため a38b69f3c で採らなかった判断のとおり採らない。
- 2026-11-09 / Claude（planner）: 一覧に無い名前の、すでにある行は、ユーザー編集画面でもプロフィールでも保存で消さずに残す（今のプロフィールと同じ。「画面が扱わない設定には触らない」）。一覧にある名前は、今と同じく画面の値で書き換える（空や「システム設定に従う」なら行を消す）。
  理由: プラグインの欄の名前はセッションから読むので、更新の直後に前から開いていた画面や、別のタブで別の画面を開いた後など、覚えた名前が保存する画面と合わないことがある。そのとき今の「全部消して入れ直す」のままだと、画面で触っていないプラグインの設定（TinyMCE 7 のツールバーなど）が消える。残す形なら、消えずに次の保存まで持ち越すだけで済む。`auth_token` のように画面の外から入れた行も、管理者の保存で消えなくなる。保存の規則がユーザー編集画面とプロフィールで 1 つになる。
  代わりの案: ユーザー編集画面は今と同じく、一覧に無い行を保存で消す。今の動きに近く、関係のない行が保存のたびに片付くが、上のセッションの食い違いで設定が消える。本人の確認を「未決事項」に挙げる。
  あわせて変わる動き: 画面で変えていない設定は、画面から送られていなくても保存済みの値を残す（今は送られていない名前の行は消える）。今の画面では、変えていない設定が送られないのは、無効にした欄が無いので起きない。
- 2026-11-09 / Claude（planner）: 言語・テーマの名前の検査、`rb_base_url` の形の統一、「システム設定と同じ値は保存しない」は、この保存で扱う名前（一覧にある名前）にだけ行う。残す行（一覧に無い名前）は値を変えずにそのまま入れ直す。
  理由: 画面が扱わない行に触らないという前の判断と同じ。今のプロフィールは残す行にも同じ処理をかけていて、プロフィールで出していない `rb_base_url` などが、プロフィールを保存したついでに形を直されたり、システム設定と同じなら消えたりする。違いは DB の行だけで、効く値は変わらない（システム設定と同じ値の行は、あってもなくても同じ値になる）。
- 2026-11-09 / Claude（planner）: 関係のない行を消すマイグレーションは足さない。
  理由: 今の画面が送る項目はすべて除外の一覧か設定のどちらかで（Surprises）、過去に漏れた名前は baseline が消している。main の除外の一覧から外れた `save_action` が入るのは、#1277 の後に古い画面から保存したときだけで、#1277 も #1304 もまだリリースしていない（タグは 1.2.4J まで）。#1304 を 1.3.0 より前に入れれば、配布する版で行ができることは無い。名前で消そうとすると、プラグインの正しい設定（`tinymce7_*` など）と見分けられない行まで消すおそれがあり、「利用者のデータを消さない」（`assets/docs/migrations.md`）にも反する。
  条件: M0 で、今の画面が送るのに除外の一覧にも設定にも無い名前が見つかったら、その名前だけを消すマイグレーションを M2 に足す（手順は Concrete Steps の M2）。
- 2026-11-09 / Claude（planner）: プラグインの欄の名前を覚えるセッションのキーは、`pluginSettingNames.user`（ユーザー編集画面）と `pluginSettingNames.profile`（プロフィール）にする（`sessionv()` はドットで入れ子の配列を読み書きする。`manager/includes/helpers.php:878`）。今の `profileSettingNames` は使わなくなる。
  理由: 2 つの画面で同じ形の名前にし、互いに上書きしない。更新の前に開いていたプロフィールから保存すると、その 1 回だけプラグインの欄が保存されない（前の判断により行は残る）。開き直せば起きない。
- 2026-11-09 / Claude（planner）: プラグインの欄として受けるのは、イベントが返した HTML の中の `input`・`select`・`textarea`・`button` の名前だけ（今のプロフィールと同じ。`script` などの中は見ない）。JS で後から足した欄や、外から POST した任意の名前は保存しない。互換を気にする外部プラグインは TinyMCE 7 だけで、TinyMCE 7 は HTML で欄を出しているので影響は無い。リリースノートに書く。

## Outcomes & Retrospective

実装後に記載。

## Context and Orientation

用語:

- **ユーザー設定**: ユーザーごとにグローバル設定を上書きする値。表 `user_settings`（列 `user`・`setting_name`・`setting_value`）に 1 設定 1 行で入る。ログイン中の管理ユーザーの行は、`DocumentParser::getSettings()` がグローバル設定に重ねる（`manager/includes/document.parser.class.inc.php:1347-1350`）。ログインの判定（`allowed_ip`・`allowed_days`・`manager_login_startup`）は `manager/processors/login.processor.functions.php` の `user_config()` が直接読む。
- **ユーザー編集画面**: 管理ユーザーの新規（`a=11`）と編集（`a=12`）。権限 `new_user` / `edit_user` で開き、`save_user` で保存する。`mode` は `new` / `edit`。
- **プロフィール**: ログイン中のユーザー自身の編集（`a=74`）。`mode` は `profile`。権限 `change_password` で保存でき、変えられる設定はユーザー編集画面より少ない（ログインの制限・ファイルの置き場所などは変えられない）。
- **設定の定義**: `manager/includes/settings_form.definition.inc.php`。グローバル設定・ユーザー編集画面・プロフィールの設定のタブを、同じ行の配列から描く。行の `scope` に `user` / `profile` があると、その画面にも出る。書き方の正は `assets/docs/global-settings.md` の「ユーザー編集画面・プロフィールにも出す行」。
- **`changed_settings`**: 画面の JS（`mutate_user/tpl/changed_settings.php`）が、送信の時に「表示の時から変わった欄の名前」を JSON で送る hidden。保存は、ここに無い設定を、表示されていた値ではなく保存済みの値のまま残す（#621。既定値や編集している管理者の値をそのまま保存しないため）。JS が動かず送られないときは、送られた値をすべて保存する。
- **「システム設定に従う」**: `userDefault` の行に付くチェックボックス `default_<名前>`（値 1）。チェックすると、そのユーザーの値を持たない（行を消す）。この行は、チェックを外せば空の値も保存できる（例: `upload_images` を空にしてそのユーザーは画像をアップロードできない）。
- **差し戻し**: 入力エラーのとき、`webAlert()`（`save_user.functions.php:295-305`）が入力をセッションに残し、ダイアログを出して画面に戻す。画面は残した値と `changed_settings` を復元する。

関係するファイル（main c5cdf5a00 の行番号）:

    manager/processors/permission/save_user.processor.php      保存の入口（a=32）。検査と newUser() / updateUser() の呼び分け
    manager/processors/permission/save_user.functions.php       saveUserMode() 7-11、profileSettingNames() 67-72、saveUserSettings() 103-259
                                                                 （$ignore 106-146、$defaults 148-154、POST の読み取り 156-170、changed_settings 171-190、
                                                                 default_ 192-197、プロフィールの絞り込み 199-208、言語・テーマ 210-221、rb_base_url 223-226、
                                                                 システム設定と同じ値 228-239、消して入れ直す 241-258）、changedSettingNames() 261-272、
                                                                 currentUserSettings() 274-282、newUser() 531 行・updateUser() 652 行で呼ぶ
    manager/includes/settings_form.class.inc.php                 EvoSettingsForm。names() 149-170、renderTab() 242-280、renderEventSection() 299-319、userDefaultBox() 753-764
    manager/includes/settings_form.definition.inc.php            user / profile の行は 686-732（表示）、904-950（エディター。タブの eventScope 897）、961-1070（ファイル）
    manager/actions/permission/mutate_user.dynamic.php           ユーザー編集画面の入口。OnUserFormPrerender 73-78（フォームの外）
    manager/actions/permission/mutate_user/tpl/form.php          設定のタブの render() 39 行、OnUserFormRender 46-54
    manager/actions/permission/mutate_user/tpl/form/tab_settings.php  手書きの 4 つの設定と、a=12 だけの属性の行
    manager/actions/permission/mutate_user_pf.dynamic.php        プロフィール。OnUserFormPrerender 131-136、render() と名前の差 319-325、
                                                                 OnUserFormRender 333-338、セッション 340、fieldNames() 352-372
    manager/actions/permission/mutate_user/tpl/changed_settings.php  changed_settings を送る JS
    assets/docs/global-settings.md                               53 行（userDefault と $defaults）、66 行（names と profileSettingNames）

DocumentParser への影響: `executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()` のどれも変えない。`getSettings()` が重ねる `user_settings` の行の中身が「画面に出している設定だけ」になるだけで、重ね方は変わらない。キャッシュ: ユーザー設定はファイルのキャッシュに入らない（`getUserConfig()` のリクエスト内の static だけ）。`updateUser()` の `clearCache()` は今のまま。`saveUserSettings()` のツリーの開閉の記憶（セッション `openedArray`）は、M3 の手順 12b で `sessionv('*openedArray', null)` にし、保存する行が 0 件のときも消すようにした。

前提: #1277（PR #1296）は main にマージ済み（2026-11-09T02:20Z、除外の一覧に `after_save`）。#1300（文書グループの付け外しの権限）は未マージで、触るファイルは重ならない（`manager/actions/document/mutate_content/`・`save_resource.processor.php`）が、着手は #1300 のマージ後とする（Interfaces and Dependencies）。

## Plan of Work

M1 では保存の結果を変えずに、名前の取り出しを `EvoSettingsForm` に集める。`names('user')` が手書きの 4 つを含むようにし、`userDefaultNames($scope)`（`userDefault` の行の名前）、`fieldNames($html)`（`mutate_user_pf.dynamic.php` から移す）、`pluginFieldNames()`（`render()` の中で呼んだイベントの出力の欄の名前）を足す。定義は `definition()` を 1 回だけ読む（`names()` と `userDefaultNames()` が同じ行の走査を使う。private の static の配列に、scope ごとの行を覚えてよい）。画面は 2 つとも、プラグインの欄の名前を `pluginSettingNames.<scope>` に覚える。保存は、プロフィールのプラグインの名前をこの新しいキーから読み、`$defaults` を `userDefaultNames()` から読む。どちらも今と同じ結果になる（プロフィールのセッションの名前から `theme_refresher`・`default_manager_inline_style` が抜けるが、今も除外の一覧と `$defaults` の処理で落ちている）。M0 の記録とまったく同じになることを確かめてから M2 へ進む。

M2 で `saveUserSettings()` を次の形にする。除外の一覧 `$ignore` と、プロフィールだけの絞り込みはなくなる。

    $scope = 保存の mode が profile なら 'profile'、それ以外は 'user'
    $allowed = EvoSettingsForm::names($scope) とセッション pluginSettingNames.<scope> の和（重複なし）
    $defaults = EvoSettingsForm::userDefaultNames($scope)
    $changed = changedSettingNames()（null は JS が動かなかった）
    $current = currentUserSettings($id)
    $allowed の名前ごとに:
        $changed が null でなく、名前も default_<名前> も $changed に無い → 保存済みの値があれば残す、無ければ持たない
        名前が $defaults にあり、POST の default_<名前> が 1 → 持たない（システム設定に従う）
        POST の値（配列は , でつなぐ）。送られていない → 持たない。$defaults に無い名前で空白だけ → 持たない
        それ以外 → その値
    言語・テーマの名前の検査、rb_base_url の統一、システム設定と同じ値を落とす（今の 210-239 行と同じ処理を、ここまでの値にだけ）
    $current のうち $allowed に無い名前の行を、値を変えずに足す
    そのユーザーの行を消して入れ直す（今と同じ）

POST から読むのは `$allowed` の名前と、`$defaults` の名前の `default_<名前>` だけになる。比較は `in_array(…, true)` で行う。値の読み取りは今と同じ `postv()`（配列を , でつなぐので、型つきのヘルパーには合わない。`request-input.md` の「加工しない文字列」に当たり、DB へは入れる直前の `db()->escape()` で安全にする今の形を変えない）。

M3 でドキュメント（`assets/docs/global-settings.md`）を新しい決まりに直し、全体を取り直して確かめる。

想定コミット（マイルストーンごとに 1 つ）:

- M1 `refactor(manager): ユーザー設定の名前の取り出しを EvoSettingsForm に集める`
- M2 `refactor(manager): 管理ユーザーの設定を保存してよい名前の一覧で受ける`（マイグレーションを足すときは同じコミットに入れる）
- M3 `docs: ユーザー設定として保存する名前の決まりを書く`

## Concrete Steps

作業ディレクトリはリポジトリのルート。git はワークツリーの隔離のため 1 コマンドずつ実行する（`cd` や `&&` でつながない）。検証環境は `adaptive-validation-runner` スキルの手順で立てる（`setup-site.js --sample --editor`、`smoke.js` が全 PASS）。以下の `<dc>` は `docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml`（名前やアドレスを変えて起動したときは、`references/environment.md` のとおり同じ環境変数を先頭に付ける）。管理画面へ curl するときは `Accept-Language` を必ず付ける。

M0 着手前の確認と修正前の記録

1. `gh pr view 1296 -R modxcms-jp/evolution-jp --json state` と `gh issue view 1300 -R modxcms-jp/evolution-jp --json state` で、#1277 と #1300 がマージ済みであることを確かめ、ブランチ `refactor/1304-user-settings-allowlist` を最新の main に合わせる。`git diff --stat origin/main -- manager/processors/permission manager/actions/permission manager/includes/settings_form.class.inc.php manager/includes/settings_form.definition.inc.php` で、Context の行番号から動いたところを確かめる。
2. 送られる項目を確かめる。main の検証環境で、Playwright で `a=11`、`a=12&id=<editor の id>`、`a=74` を開き、保存のボタンで送った POST の本文（`page.on('request')` の `postData()`）の名前を控える。TinyMCE 7 が有効であること（エディターのタブに `tinymce7_toolbar_preset` があること）も確かめる。
   期待: どの名前も、`save_user.functions.php` の `$ignore`・`$defaults` の `default_*`・Surprises の 13 + 4 個の設定・TinyMCE 7 の 3 個のどれかに入る。入らない名前があれば Surprises に書き、M2 の手順 9 でマイグレーションを足す。
3. 修正前の記録を取る。main の検証環境で、Validation and Acceptance の表の S1〜S17 を順に行い、各操作の後に次を実行して、「シナリオ id・user・setting_name・setting_value」の TSV に追記する（editor の id は `SELECT id FROM modx_manager_users WHERE username='editor'`）。
       <dc> exec app php evo db:query "SELECT user, setting_name, setting_value FROM modx_user_settings ORDER BY user, setting_name"
   画面の操作は Playwright（`changed_settings` は JS が作るので、画面のボタンで保存する）。偽の項目（S8・S13）は、画面を開いて `DOMContentLoaded` の後に `page.evaluate()` で hidden の input をフォームに足してから保存のボタンを押す。JS を通さない保存（S10）は、画面から `csrf_token` を読み、ログインの Cookie を付けて `page.request.post('index.php?a=32', { form: … })` で送る。各シナリオの前に、対象のユーザーの行をシナリオの「前提」の状態にする（`db:query` の `DELETE` / `INSERT`）。あわせて、S18 のために `a=11`・`a=12&id=<editor>`・`a=74` の `document.userform.elements` の `[name, type, value, checked]` の一覧を JSON で控える（`csrf_token` の値は伏せる）。
   置き場所: `/home/yamamoto/.claude/jobs/62dfc916/tmp/1304-m0-settings.tsv`・`1304-m0-forms.json`、スクリプトは同じ所の `1304-m0.js`（ジョブの一時領域。git の管理外で消えることがある。消えていたら main のこのときのコミットを立てて取り直す）。基準のコミットを Artifacts and Notes に書く。

M1 名前の取り出しを `EvoSettingsForm` に集める（保存の結果は変えない）

4. `manager/includes/settings_form.class.inc.php`:
   - 定数 `USER_TAB_KEYS = ['allow_manager_access', 'allowed_parents', 'allowed_ip', 'allowed_days']` を足す。docblock に「ユーザー編集画面の『ユーザー』タブ（`mutate_user/tpl/form/tab_settings.php`）が手書きで出す設定。欄を足したらここにも足す」と書く。
   - `names()`（149-170）を、`user` のときは定義の行の名前に `USER_TAB_KEYS` を足して返す形にする。docblock を「その scope の画面に出す設定キー。ユーザー設定として保存してよい本体の名前（プラグインの欄は含まない）」に直す。
   - `userDefaultNames(string $scope): array` を足す（その scope の行のうち `userDefault` が真のものの `key`）。
   - 定義の走査は `names()` と共有し、`definition()` を 1 回のリクエストで 1 回だけ読む。
   - `public static function fieldNames(string $html): array` を、`mutate_user_pf.dynamic.php:349-372` のコメントと中身のまま移す。
   - プロパティ `private $pluginFieldNames = [];` と `public function pluginFieldNames(): array` を足す。`renderEventSection()`（299-319）で、出力が空でないときに `self::fieldNames($output)` を足していく。
5. `manager/actions/permission/mutate_user_pf.dynamic.php`:
   - 135 行と 337 行の `fieldNames(` を `EvoSettingsForm::fieldNames(` にする。
   - 319-325 行を、`$settingsHtml = $settingsForm->render(); echo $settingsHtml;` の後で `$pluginSettingNames = array_merge($pluginSettingNames, $settingsForm->pluginFieldNames());` にする（`names('profile')` との差を取らない）。
   - 339-340 行を `sessionv('*pluginSettingNames.profile', array_values(array_unique($pluginSettingNames)));` にし、コメントの関数名を `allowedSettingNames()` に直す（M1 の時点では `profileSettingNames()`。M2 で直す）。
   - 349-372 行の `fieldNames()` を消す。
6. `manager/actions/permission/mutate_user/tpl/form.php`:
   - 39 行の `<?= $settingsForm->render() ?>` はそのまま（`render()` の中で名前が集まる）。
   - 46-54 行の `OnUserFormRender` のあとで、`$settingsForm->pluginFieldNames()` と、イベントの出力があれば `EvoSettingsForm::fieldNames(implode('', $evtOut))` を合わせ、`sessionv('*pluginSettingNames.user', array_values(array_unique(…)))` に覚える。コメントで「保存（`save_user.functions.php`）が、プラグインが出した欄だけを設定として受けるため」と書く。
7. `manager/processors/permission/save_user.functions.php`:
   - 先頭（ログインの確認の後）で `require_once MODX_CORE_PATH . 'settings_form.class.inc.php';` を読み、`profileSettingNames()` の中の `require_once` を消す。
   - `userSettingScope()`（`saveUserMode() === 'profile' ? 'profile' : 'user'`）を足す。
   - `profileSettingNames()` を `sessionv('pluginSettingNames.profile', [])` を足す形にする。
   - `$defaults`（148-154）を `EvoSettingsForm::userDefaultNames(userSettingScope())` にする。プロフィールでは `manager_inline_style` だけになるが、`upload_*` はプロフィールの許可リストに無いので結果は同じ。
   `manager/actions/permission/mutate_user/tpl/form/tab_settings.php` の先頭に「ここに設定の欄を足したら `EvoSettingsForm::USER_TAB_KEYS` にも足す（保存はその一覧の名前だけを受ける）」とコメントを書く。
8. 構文と結果を確かめる。
       <dc> exec app php -l manager/includes/settings_form.class.inc.php
       docker run --rm -v "$PWD":/app -w /app php:7.4-cli php -l manager/includes/settings_form.class.inc.php
   変えたファイルごとに同じことを行う。期待: `No syntax errors detected`。
   M0 の手順 3 のスクリプトで S1〜S18 を取り直し（出力先を `1304-m1-*` に替える）、`diff` で M0 の記録と比べる。期待: 差が無い（id を伏せたうえで完全一致）。`<dc> exec app php evo log:tail --lines=20` に PHP のエラーが無い。

M2 保存してよい名前の一覧で受ける

9. `manager/processors/permission/save_user.functions.php`:
   - `profileSettingNames()` を `allowedSettingNames($scope)` に置き換える（`EvoSettingsForm::names($scope)` とセッション `pluginSettingNames.<scope>` の和、`array_values(array_unique())`）。
   - `saveUserSettings()` を Plan of Work の形に書き直す。`$ignore`（106-146）、POST を総なめする 2 つのループ（156-190）、`default_` の後始末（192-197）、プロフィールだけの絞り込み（199-208）を消す。言語・テーマ・`rb_base_url`・システム設定と同じ値の処理（210-239）は、そのまま一覧の名前の値にかける。その後で、`$current` のうち一覧に無い名前を値のまま足す。消して入れ直す処理（241-258）は変えない。
   - 関数の先頭のコメントに、受ける名前の正が `EvoSettingsForm`（定義の `scope`・`userDefault`・`USER_TAB_KEYS`）と、画面が覚えたプラグインの欄であること、一覧に無い行は残すことを書く。
   - `mutate_user_pf.dynamic.php` のセッションの行のコメントの関数名を `allowedSettingNames()` に直す。
   マイグレーション（M0 の手順 2 で漏れが見つかったときだけ）: `<dc> exec app php evo make:migration remove_non_setting_user_settings` で雛形を作り、見つかった名前だけを、baseline の 355-363 行と同じ形（行があるときだけ `DELETE`、`log()` で 1 行）で消す。`<dc> exec app php evo migrate` で `Applied …`、もう一度で `Nothing to migrate.` になることを確かめる。
10. 構文（手順 8 と同じ）。`git grep -n "\$ignore\|profileSettingNames" -- manager` が出力なし、`git grep -n "function fieldNames" -- manager` が `settings_form.class.inc.php` の 1 件だけ。
11. M0 のスクリプトで S1〜S18 を取り直し（`1304-m2-*`）、M0 の記録と比べる。期待: 差は Validation and Acceptance の表の「変更後」で「main と違う」と書いた行（S8・S10・S15）だけ。PHP のエラーが無い。

M3 ドキュメントと最終確認

12. `assets/docs/global-settings.md`:
    - 53 行の `userDefault` の説明から「保存の処理の `$defaults` にも同じキーが要る」を消し、「保存は `EvoSettingsForm::userDefaultNames()` でこの行を読み、`default_<キー>` を受ける」にする。
    - 66 行を、「`EvoSettingsForm::names($scope)` は、その scope の画面に出す設定キー（`user` は、ユーザー編集画面の『ユーザー』タブが手書きで出す `USER_TAB_KEYS` を含む）。ユーザー編集画面・プロフィールの保存（`save_user.functions.php` の `allowedSettingNames()`）は、これに、画面を表示したときにプラグインのイベントの出力から取った欄の名前（セッション `pluginSettingNames.<scope>`）を足した名前だけをユーザー設定として受ける。一覧に無い名前の行は、保存しても消さずに残す」という趣旨に直す。
    - 同じ節に、プラグインが JS で足した欄はユーザー設定に入らないこと（イベントの HTML に欄を書く）を 1 行足す。
    - 同じ節に、プラグインの欄に本体のフォームと同じ名前（`csrf_token`・`mode`・`userid` など）を使わないこと（そのままユーザー設定として保存されるため）を 1 行足す（M2 の自己レビューより）。
    `doc-audit` の手順で、変えたファイルのパスの表記と参照を確かめる。
    - `tab_settings.php:2` と `mutate_user/tpl/form.php:57` のコメントが、M2 の後の動きと合っていることを確かめる。
    - `assets/docs/core-issues.md` に、`tab_settings.php` の 4 つの設定（`USER_TAB_KEYS`）を設定の定義に移す課題を書く。課題には、`USER_TAB_KEYS` と画面が実際に送る名前（記録のスクリプトの S18 のフォームの欄）を突き合わせて確かめる手順も残す（M1 の自己レビューより）。
12b. `manager/processors/permission/save_user.functions.php` の `saveUserSettings()`: 保存する行が 0 件のときに早く戻るため、セッションの `openedArray` を消す処理に届かない（修正前から）。消す処理を早期リターンより前に置き、`$_SESSION` を直接触らず `sessionv()` で消す（動きは、0 件のときも消すようになる以外は変えない。M2 の自己レビューより）。
13. 全体を取り直す（`1304-m3-*`）。M2 の記録と同じであること。あわせて、a=12 でセッションに `pluginSettingNames.user` が無いときに TinyMCE 7 の欄を変えて保存すると、既存の `tinymce7_*` の行が残りエラーが出ないこと、一覧に無い行の値に `'`・`\`・空文字を入れておき a=12 で保存しても値が変わらないことを確かめる（M2 の自己レビューより）。PHP 7.4 の検証環境（`references/environment.md` の PHP 7.4 の節）で S2・S3・S5・S7・S8・S12・S13 を行い、PHP 8 側と同じ行になることを確かめる。
14. PR の本文に、Validation の表（main と変更後）と、Interfaces のリリースノートの文案を写す。

## Validation and Acceptance

Docker の検証環境で、次の操作のあとの `user_settings` の行（手順 3 の `db:query`）を、main（M0 の記録）と変更後で比べる。E は `setup-site.js --editor` が作るユーザー `editor`、A は管理者。ユーザー編集画面の操作は A がログインして行う。「同じ」は、行の名前と値が main と一致すること。

| id | 前提と操作 | main | 変更後 |
| --- | --- | --- | --- |
| S1 | `a=11` で新しいユーザーを、設定のタブに触らずに保存 | 記録した行（設定の行は無い見込み） | 同じ |
| S2 | E の行なし。`a=12` で `manager_theme` を別のテーマにして保存 | `manager_theme` の 1 行 | 同じ |
| S3 | `a=12` で `allowed_parents=1,2`、`allowed_ip=127.0.0.1`、`allowed_days` を日・月、`allow_manager_access` を「いいえ」にして保存 | 4 行（`allowed_days` は `1,2`） | 同じ |
| S4 | S3 のあと、`allowed_days` をすべて外し、`allowed_parents` を空にして保存 | `allowed_days`・`allowed_parents` の行が消え、ほかは残る | 同じ |
| S5 | `upload_images` の「システム設定に従う」を外し、値を空にして保存 | `upload_images` が空の値で 1 行 | 同じ |
| S6 | S5 のあと、「システム設定に従う」をチェックして保存 | `upload_images` の行が消える | 同じ |
| S7 | エディターのタブで TinyMCE 7 の「ツールバー構成」をシンプル、メニューバーを表示しないにして保存 | `tinymce7_toolbar_preset`・`tinymce7_menubar` の行 | 同じ |
| S8 | `a=12` を開いてから、hidden の `foo=bar`・`save_action=stay`・`stay=2`・`site_name=X` をフォームに足し、`manager_theme` を変えて保存 | `foo`・`save_action`・`stay`・`site_name` の行ができる（`site_name` の行は、E がログインしたときの `config('site_name')` を上書きする） | `manager_theme` の行だけ。足した 4 つの行はできない（main と違う） |
| S9 | E に `manager_language=english` の行を入れておき、`a=12` で何も変えずに保存 | `manager_language` の行が残る（`changed_settings`） | 同じ |
| S10 | `changed_settings` を送らずに a=32 へ直接 POST（`mode=edit`、`userid=E`、画面の値に `manager_theme`・`allowed_ip`・`foo=bar` を足したもの） | 送った設定がすべて行になり（システム設定と同じ値を除く）、`foo` の行もできる | `foo` の行ができない（main と違う）。ほかは同じ |
| S11 | `a=12` で `manager_theme` を変え、パスワードを変えるにして確認の欄を違う値にして保存（差し戻し）。戻った画面でパスワードを直して保存 | ダイアログのあと `a=12` に戻り、テーマの選択が残る。保存で `manager_theme` の行 | 同じ |
| S12 | A の `a=74` で `manager_inline_style` を書き、TinyMCE 7 のメニューバーを表示しないにして保存 | `manager_inline_style`・`tinymce7_menubar` の行 | 同じ |
| S13 | A に `a=12` で `upload_maxsize` の行を作っておく。A の `a=74` を開き、hidden の `allow_manager_access=0`・`allowed_parents=5`・`rb_base_dir=/tmp/`・`foo=bar` を足して保存 | 足した 4 つは行にならず、`upload_maxsize` の行が残る | 同じ |
| S14 | S12 のあと、`a=74` で `manager_inline_style` の「システム設定に従う」をチェックして保存 | `manager_inline_style` の行が消える | 同じ |
| S15 | E に `auth_token=test1304` と `foo=bar` の行を DB で入れておき、`a=12` で `manager_theme` を変えて保存。続けて A に同じ 2 行を入れ、`a=74` で保存 | `a=12` では 2 行とも消える。`a=74` では残る | どちらでも残る（`a=12` は main と違う。Decision Log の 2 つ目と、未決事項の節のメインの決定） |
| S16 | a=32 へ `manager_language=../x`・`manager_theme=nosuch` を送る（`changed_settings` に含める） | 行にならない | 同じ |
| S17 | `rb_base_url` に `http://<サイトの URL>/assets/` を入れて保存 | 相対パス `assets/` の行 | 同じ |
| S18 | `a=11`・`a=12`・`a=74` のフォームの欄（名前・種類・値・チェック） | 記録した一覧 | 同じ（画面は変えない） |

あわせて確かめること:

- S7 を、`a=12` を開いたあとに別のタブで `a=74` を開いてから保存しても、TinyMCE 7 の行が S7 と同じになる（セッションのキーが分かれている）。
- 保存の操作のあとに `<dc> exec app php evo log:tail --lines=20` に PHP のエラーが無い。ブラウザのコンソールにエラーが無い。
- 静的な確認: 手順 10 の grep。変えたすべての PHP ファイルで、`<dc> exec app php -l` と `php:7.4-cli` の `php -l` が `No syntax errors detected`。

## Idempotence and Recovery

M1・M2 はコードだけの変更で、表の形は変えない。M1 は保存の結果を変えないので、M1 だけを残して止めてもよい。問題が出たら、そのマイルストーンのコミットを revert する（M2 は M1 に依存するので、戻すときは M2 から）。M2 を戻すと、M2 の間に「一覧に無いので残した」行は、次に `a=12` で保存したときに今までどおり消える。マイグレーションを足したときは冪等に書く（行があるときだけ消す）ので、何度流しても 2 回目以降は何もしない。セッションに残った古い `profileSettingNames` は読まなくなるだけで、エラーにならない。M0 の記録とスクリプトは git の管理外の一時領域にあり、消えていたら手順 3 の取り方で、Artifacts and Notes に書いた基準のコミットを立てて取り直す。

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/1304
- ブランチ: `refactor/1304-user-settings-allowlist`（`gh issue develop` で #1304 に紐づけ済み。base `main`）。PR: #1315（下書き。2026-11-09 に M1 まで入れて作成。最後のマイルストーンで下書きを解除する）。
- 関連: #1277（PR #1296、マージ済み。保存後の行き先を `after_save` にし、除外の一覧を書き換えた）、#1300（未マージ。文書グループの権限。ファイルは重ならない）、#611・#615・#640（漏れた名前の掃除。baseline の 355-363 行）、#621（`changed_settings`）、a38b69f3c（プロフィールのプラグインの欄の名前をセッションに覚える）。
- 修正前の記録: 基準のコミット 0c8f41e18。ジョブの一時領域 `/home/yamamoto/.claude/jobs/62dfc916/tmp/` の `1304-m0-user_settings.tsv`（S1〜S17 のユーザー設定の行）・`1304-m0-forms.json`（a=11・a=12・a=74 のフォームの欄）・`1304-m0-post-names.json`（送られる項目名）。記録のスクリプトは同じ場所の `1304-m0.js`・`run1304.sh`（`bash run1304.sh <OUT 接頭辞>`）・`env1304.sh`・`setup1304.sh`。一時領域が消えたら、0c8f41e18 で取り直す。
- 想定コミット単位は Plan of Work の 3 つ。

## Interfaces and Dependencies

リリースノートに書くこと:

- 管理ユーザーの編集画面（`a=11`・`a=12`）の保存は、ユーザー設定として、画面に出している設定だけを受ける（グローバル設定の定義でユーザーの画面に出す行、「ユーザー」タブの 4 つの設定（管理画面へのログインの許可・ツリーの起点・許可する IP・曜日）、プラグインが画面の HTML に出した欄）。それ以外の POST の項目はユーザー設定に入らない。プロフィール（`a=74`）は前から同じ。プラグインの欄を JS で後から足している場合や、a=32 へ任意の名前を POST してユーザー設定にしていた場合は、保存されなくなる（イベントの出力の HTML に欄を書けば保存される）。
- ユーザーを保存しても、画面に出していない設定の行（DB に直接入れた `auth_token` など）を消さなくなる（ユーザー編集画面・プロフィールとも）。不要になった行（使わなくなったプラグインの設定や、以前に保存の処理へ直接送られて入った名前など）は、画面の保存では消えないので、消すときは DB を直接操作する。
- プラグインがユーザーの画面に出す欄の名前は、そのままユーザー設定として保存される。本体のフォームと同じ名前（`csrf_token`・`mode`・`userid` など）をプラグインの欄に使わない。
- 更新の前に開いていたユーザー編集画面・プロフィールから更新の後に保存すると、その 1 回だけプラグインの欄（TinyMCE 7 のツールバー構成など）が保存されない（行は残る）。開き直せば起きない。
- ユーザーを保存すると、保存する設定の行が 0 件になったときも、保存した管理者のツリーの開閉の記憶を消し、次に開いたときに設定から作り直す（これまでは 1 件以上のときだけ消していた）。
- 開発者向け: 設定の定義に `userDefault` の行を足すとき、保存の処理に名前を足す必要は無くなった。`mutate_user/tpl/form/tab_settings.php` に設定の欄を足すときは `EvoSettingsForm::USER_TAB_KEYS` に足す。`mutate_user_pf.dynamic.php` のグローバル関数 `fieldNames()` は `EvoSettingsForm::fieldNames()` になった。セッションの `profileSettingNames` は `pluginSettingNames.profile` になった。

変えないもの: 表 `user_settings` の形、Web ユーザーの保存（`save_web_user.processor.php` の許可リスト）、プラグインのイベント（`OnBeforeUserFormSave`・`OnManagerSaveUser`・`OnUserFormSave` の引数と呼ぶ順）、画面の見た目と欄。

依存:

- #1277（PR #1296）: main にマージ済み（2026-11-09 確認）。
- #1300: 着手はマージの後（依頼の条件）。触るファイルは重ならない。
- #1304 は 1.3.0 のリリースより前に入れる。#1277 だけがリリースに入ると、更新の前に開いていた画面からの保存で `save_action` の行ができる（Decision Log のマイグレーションの判断の前提）。

## 未決事項

なし。2026-11-09 にメインが次のとおり決めた（方針: 判断の材料が揃っているものは AI が決め、理由を残す）。

- 2026-11-09（メイン）: 一覧に無い名前のすでにある行は、ユーザー編集画面の保存でも残す（計画どおり）。`auth_token` やプラグインの設定が管理者の保存で消えなくなり、保存の規則がプロフィールと 1 つになるため。
- 2026-11-09（メイン）: `tab_settings.php` の 4 つの設定を設定の定義に移す作業は、画面の組み替えになるのでこの Issue の外とし、`assets/docs/core-issues.md` に課題として書く（M3 で行う）。
- 2026-11-09（メイン）: #1300（PR #1312）とは触るファイルが重ならないので、マージを待たずに着手してよい。#1277 は main にマージ済み（14fab03b6）。
