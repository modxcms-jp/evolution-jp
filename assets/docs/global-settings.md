# グローバル設定の拡張

新しいシステム設定を追加する際の手順。

## 1. デフォルト値の定義

`manager/includes/default.config.php` に追加:

```php
'new_setting_name' => 'default_value',
```

## 2. 言語ファイルへの追加

`manager/includes/lang/japanese-utf8.inc.php` と `english.inc.php` に追加:

```php
"setting_new_setting_name" => '設定名',
"setting_new_setting_name_desc" => '設定の説明文',
```

## 3. 設定画面への表示

画面は `manager/includes/settings_form.definition.inc.php`（設定の定義）を、`manager/includes/settings_form.class.inc.php` の `EvoSettingsForm` が HTML にして出す。HTML は手で書かず、定義のタブの `rows` に 1 行足す:

```php
[
    'key' => 'new_setting_name',
    'type' => 'yesno',
    'label' => lang('setting_new_setting_name'),
    'desc' => lang('setting_new_setting_name_desc'),
],
```

- `key`: 設定キー。送る欄の名前になり、値は `system_settings` の同じ名前から出る
- `type`: `text`・`textarea`・`radio`・`yesno`（はい／いいえ）・`enabled`（有効／無効）・`select` のほか、特別な行の型（`path`・`lang_text`・`content_types`・`template`・`topmenu`・`password`）
- `label`・`desc`: 見出しと説明。言語ファイルとコードからだけ来る信頼する HTML として出る。DB や環境の値を文に差し込むときは、`desc` に関数（`static function (array $values) { … }`）を書き、その中で `hsc()` する
- `options`: `radio`・`select` の `[値 => 表示]`（`select` は `[グループ名 => [値 => 表示]]` で optgroup）。DB やファイルから取る一覧（テーマ・言語・ロールなど）は関数にする（`EvoSettingsForm::themes()`・`roles()`・`languages()`）。値と表示は `hsc()` される
- `default`: 値が無い・空のときの値（文字列か関数）。`size`: `text` の maxlength（10 以下なら欄の幅を em で決める）。`attr`: 欄に足す属性 `[名前 => 値]`（値は `hsc()` される）
- `showIf`: `['use_browser', '1']` のように書くと、そのラジオがその値のときだけ行を出す。出し入れは `manager/actions/tool/mutate_settings/functions.js` が `data-show-if` を見て行う。プラグインが出す HTML も同じ属性を付ければ、読み込み時とラジオを切り替えたときに一緒に出し入れされる
- タブの中を小見出しで分けるときは、`sections` の section の `title` に `lang('settings_section_…')` を書く。`<h3 class="settings-section">` の次に表が続く形で出る（`null` なら見出しなしの 1 つの表）。見た目は `manager/media/style/common/shell.css` の `.tab-page .settings-section` の 2 つの規則（小見出しの `.tab-page .settings-section` と、直前の表の下の余白の `.tab-page > :has(+ .settings-section)`）で決まり、テーマには足さない。文言は `label` と同じ信頼する HTML
- 設定でない欄（hidden など）を足したときは、`save_settings.processor.php` の `nonSettingKeys()` に入れて保存しないようにする。設定の欄を足すだけなら触らない

入力エラーで保存が止まって画面に戻ったときの打った値は、`EvoSettingsForm::systemValues()` が行に重ねるので、行ごとの対応は要らない。ただし `password` 型（`smtppw`）は伏せ字のまま。

### ユーザー編集画面・プロフィールにも出す行

ユーザーごとに変えられる設定（`user_settings` に入る）は、同じ行をユーザー編集画面（`a=11`・`a=12`）とプロフィール（`a=74`）でも描く。画面側は手書きせず、`new EvoSettingsForm(EvoSettingsForm::definition(), <そのユーザーの値>, EvoSettingsForm::storedValues(), 'user')`（プロフィールは `'profile'`）の `render()` を出す。`<そのユーザーの値>` は差し戻し込みの値、`storedValues()` はシステム設定の値（条件の判定と「システム設定に従う」の先の値）。`table.settings` の共通の見た目は `EvoSettingsForm::style()` を出す。

行の項目:

- `scope`: その行を出す画面の配列（`system`・`user`・`profile`。既定は `['system']`）。ユーザーに変えさせる行に `user`・`profile` を足す。ユーザーにしか無い設定（`manager_login_startup`）は `['user', 'profile']` だけにする
- `userDefault`: `true` にすると、「システム設定に従う」のチェックボックス `default_<キー>` を付ける。ユーザーの値が無いときにチェックが付き、差し戻した画面では送ったときのチェックを優先する。保存の処理の `$defaults`（`save_user.functions.php` の `saveUserSettings()`）にも同じキーが要る
- `userAttr`: user・profile のときだけ欄に足す属性（`attr` に重なる）
- `userValue`: user・profile の欄の値を決める関数 `function (array $values, array $stored): string`（`$values` はそのユーザーの値、`$stored` はシステム設定）。ユーザーの値が無いときにシステム設定の値を見せたいときに使う
- `userDesc`: user・profile のときの説明（`desc` の代わり）
- タブの `eventScope`: そのタブの `event` を呼ぶ画面の配列（既定は `['system']`）。エディターのタブだけ `['system', 'user', 'profile']`（`OnInterfaceSettingsRender` の欄をユーザー画面にも出す）

user・profile の描き方（グローバル設定との違い）:

- その scope の行だけを、グローバル設定と同じタブ名・小見出し・順で出す。行の無い小見出しとタブは出さない
- 条件の行（`showIf`）は、システム設定の値で初めから出すか隠すかだけを決め、切り替えはしない。小見出しの行がすべて隠れるときは小見出しと表も隠し、タブの中がすべて隠れるときはタブの見出しも隠す。行は送るために残す（保存は送られた値から作り直すので、送らないと設定が消える）
- `select` の先頭に「システム設定に従う」（値は空）を足し、空の値を `default` で補わない。`path` 型は既定値の表示とリセットの無い文字の欄になる
- `$names = EvoSettingsForm::names('profile')` は、その scope に出す設定キー。プロフィールで保存してよい名前（`save_user.functions.php` の `profileSettingNames()`）は、これにプラグインの欄の名前（画面を表示したときにセッションへ覚える）を足したもの

ユーザー画面のタブ構成: `a=12` は 管理画面（表示）・エディター・ファイル（ファイルピッカー・ファイルマネージャー・アップロード）、`a=74` は 管理画面（表示）・エディター。ユーザーごとに変えられない行（HTML エクスポート、システム全体の設定）は出ない。

## 4. 設定値の反映

- **新規インストール**: `default.config.php` の値が自動的に使用される
- **既存インストール**: グローバル設定を一度保存すると `save_settings.processor.php` が反映

## 設定画面のタブ構成

すべて `manager/includes/settings_form.definition.inc.php` に書かれている。タブの id は `settingsTab` に英語の名前を付けたもの。「末尾のイベント」は、タブの定義の `event`（`'event' => 'OnSiteSettingsRender'`）で、そのタブの末尾に出るプラグインの欄。最後の小見出しの表とは別の表に出し、タブに小見出しがあるときだけ小見出し「プラグイン」（`settings_section_plugins`）を付ける。出力が空なら何も出さない。`OnSystemSettingsRender` だけは全タブの後ろ・表の外で呼ぶ。

| タブの id | タブ名（言語キー） | 小見出し（`settings_section_*`） | 末尾のイベント |
| --- | --- | --- | --- |
| `settingsTabSite` | サイト（`settings_site`） | 基本・URL・特別なページ・文字コードと時刻 | `OnSiteSettingsRender` |
| `settingsTabResources` | リソース（`settings_resources`） | 新しいリソースの初期値・作成と編集 | |
| `settingsTabCache` | キャッシュ（`settings_cache`） | なし | |
| `settingsTabFurls` | フレンドリーURL（`settings_furls`） | なし | `OnFriendlyURLSettingsRender` |
| `settingsTabSecurity` | セキュリティ（`settings_users`） | ユーザーとグループ・ログイン・エラー・テンプレート変数 | `OnUserSettingsRender` |
| `settingsTabMail` | メール（`settings_mail`） | 送信・メールの文面 | |
| `settingsTabManager` | 管理画面（`settings_ui`） | 表示・ツリー・メニュー・件数・その他 | |
| `settingsTabEditor` | エディター（`settings_editor`） | なし | `OnInterfaceSettingsRender` |
| `settingsTabFiles` | ファイル（`settings_files`） | ファイルピッカー・ファイルマネージャー・アップロード・HTMLエクスポート | `OnMiscSettingsRender` |

- ユーザー編集画面（`a=12`）とプロフィール（`a=74`）の設定のタブも、同じ定義から同じタブ名・小見出し・順で描く（`a=12` は管理画面・エディター・ファイル、`a=74` は管理画面・エディター）。行の書き方は「3. 設定画面への表示」の「ユーザー編集画面・プロフィールにも出す行」
- 設定を足すときは、行の役割に合うタブと小見出しに置く。合うものが無ければ小見出しを足す（`settings_section_…` を日本語と英語の言語ファイルの両方に足す）
- `OnSiteSettingsRender` はサイトのタブで 1 回だけ呼ぶ。プラグインの欄は同じフォームで送られ、同じ保存の処理で `system_settings` に入る
- `showIf` の初めの表示はサーバーが行で決めるが、`functions.js` が読み込み時にも 1 回、ラジオの選択に合わせて `data-show-if` の要素を出し入れする。プラグインの出力（TinyMCE 7 の表など）も、`use_editor` が「いいえ」なら初めから隠れる

## 注意事項

- SQLファイルでの設定追加は不要（`default.config.php` のみで管理）
- `save_settings.processor.php` は POST データと `default.config.php` をマージし `REPLACE INTO` で保存
- 設定でない項目の扱いは「3. 設定画面への表示」の `nonSettingKeys()` を参照
- 設定値の取得は `config('setting_name', $default)`（`evo()->config()` と同じ）を正とする。値が無いときの既定値は `null`。`evo()->getConfig()` は既定値が `''` の別の読み方で、同じ値を返す
- 管理画面では、設定値を `extract()` でグローバル変数にしない。`global $site_url`・`$GLOBALS['manager_theme']`・`$modx->event->getGlobalVariable('…')` では読めない。ユーザーごとの設定は重ねた後の値が返る
- プラグイン・モジュールも `evo()->config('キー')` で読む。消した `settings.inc.php`・`user_settings.inc.php` は include しない。同梱のプラグイン・モジュール（TinyMCE 7 を含む）で、これらの変数に頼るものが無いことは確かめてある
