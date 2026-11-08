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
- `type`: `text`・`textarea`・`radio`・`yesno`（はい／いいえ）・`enabled`（有効／無効）・`select`・`event`（プラグインの出力）のほか、特別な行の型（`path`・`lang_text`・`content_types`・`template`・`topmenu`・`password`）
- `label`・`desc`: 見出しと説明。言語ファイルとコードからだけ来る信頼する HTML として出る。DB や環境の値を文に差し込むときは、`desc` に関数（`static function (array $values) { … }`）を書き、その中で `hsc()` する
- `options`: `radio`・`select` の `[値 => 表示]`（`select` は `[グループ名 => [値 => 表示]]` で optgroup）。DB やファイルから取る一覧（テーマ・言語・ロールなど）は関数にする（`EvoSettingsForm::themes()`・`roles()`・`languages()`）。値と表示は `hsc()` される
- `default`: 値が無い・空のときの値（文字列か関数）。`size`: `text` の maxlength（10 以下なら欄の幅を em で決める）。`attr`: 欄に足す属性 `[名前 => 値]`（値は `hsc()` される）
- `showIf`: `['use_browser', '1']` のように書くと、そのラジオがその値のときだけ行を出す。出し入れは `manager/actions/tool/mutate_settings/functions.js` が `data-show-if` を見て行う。プラグインが出す HTML も同じ属性を付ければ、ラジオを切り替えたときには一緒に出し入れされる。ただし初めの表示はサーバーが行の `showIf` で決めるため、プラグインの出力には当たらない
- タブの中を小見出しで分けるときは、`sections` の section の `title` に `lang('settings_section_…')` を書く。`<h3 class="settings-section">` の次に表が続く形で出る（`null` なら見出しなしの 1 つの表）。見た目は `manager/media/style/common/shell.css` の `.tab-page .settings-section` の 1 つの規則で決まり、テーマには足さない。文言は `label` と同じ信頼する HTML
- 設定でない欄（hidden など）を足したときは、`save_settings.processor.php` の `nonSettingKeys()` に入れて保存しないようにする。設定の欄を足すだけなら触らない

入力エラーで保存が止まって画面に戻ったときの打った値は、`EvoSettingsForm::systemValues()` が行に重ねるので、行ごとの対応は要らない。ただし `password` 型（`smtppw`）は伏せ字のまま。

## 4. 設定値の反映

- **新規インストール**: `default.config.php` の値が自動的に使用される
- **既存インストール**: グローバル設定を一度保存すると `save_settings.processor.php` が反映

## 設定画面のタブ構成

すべて `manager/includes/settings_form.definition.inc.php` に書かれている。

| タブの id | タブ名（言語キー） |
| --- | --- |
| `tabPage2` | サイト（`settings_site`） |
| `tabPageDoc` | リソース設定（言語キーでなく、定義に直書きの見出し） |
| `tabPageCache` | キャッシュ設定（言語キーでなく、定義に直書きの見出し） |
| `tabPage3` | フレンドリーURL（`settings_furls`） |
| `tabPage4` | セキュリティ（`settings_users`） |
| `tabPage5` | 管理画面の設定（`settings_ui`） |
| `tabPage7` | ファイルマネージャーの設定（`settings_misc`） |

## 注意事項

- SQLファイルでの設定追加は不要（`default.config.php` のみで管理）
- `save_settings.processor.php` は POST データと `default.config.php` をマージし `REPLACE INTO` で保存
- 設定でない項目の扱いは「3. 設定画面への表示」の `nonSettingKeys()` を参照
- 設定値の取得: `evo()->getConfig('setting_name', 'default')`
