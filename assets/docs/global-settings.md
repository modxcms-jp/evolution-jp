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

- ユーザー編集画面（`a=12`）の設定のタブの見出しも同じ言語キーを使う（`settings_ui`・`settings_files`）。プロフィール（`a=74`）は `settings_ui` だけ
- 設定を足すときは、行の役割に合うタブと小見出しに置く。合うものが無ければ小見出しを足す（`settings_section_…` を日本語と英語の言語ファイルの両方に足す）
- `OnSiteSettingsRender` はサイトのタブで 1 回だけ呼ぶ。プラグインの欄は同じフォームで送られ、同じ保存の処理で `system_settings` に入る
- `showIf` の初めの表示はサーバーが行で決めるが、`functions.js` が読み込み時にも 1 回、ラジオの選択に合わせて `data-show-if` の要素を出し入れする。プラグインの出力（TinyMCE 7 の表など）も、`use_editor` が「いいえ」なら初めから隠れる

## 注意事項

- SQLファイルでの設定追加は不要（`default.config.php` のみで管理）
- `save_settings.processor.php` は POST データと `default.config.php` をマージし `REPLACE INTO` で保存
- 設定でない項目の扱いは「3. 設定画面への表示」の `nonSettingKeys()` を参照
- 設定値の取得: `evo()->getConfig('setting_name', 'default')`
