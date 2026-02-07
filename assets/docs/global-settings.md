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
$_lang['setting_new_setting_name'] = '設定名';
$_lang['setting_new_setting_name_desc'] = '設定の説明文';
```

## 3. 設定画面への表示

`manager/actions/tool/mutate_settings/tab*.inc.php` のいずれかに追加:

```php
<tr>
    <th><?= lang('setting_new_setting_name') ?></th>
    <td>
        <?= wrap_label(
            lang('yes'),
            form_radio('new_setting_name', 1, config('new_setting_name') == 1)
        ); ?><br/>
        <?= wrap_label(
            lang('no'),
            form_radio('new_setting_name', 0, config('new_setting_name') == 0)
        ); ?><br/>
        <?= lang('setting_new_setting_name_desc') ?>
    </td>
</tr>
```

## 4. 設定値の反映

- **新規インストール**: `default.config.php` の値が自動的に使用される
- **既存インストール**: グローバル設定を一度保存すると `save_settings.processor.php` が反映

## 設定画面のタブ構成

| ファイル | タブ名 |
| --- | --- |
| `tab1_site_settings.inc.php` | サイト設定 |
| `tab1_doc_settings.inc.php` | リソース設定 |
| `tab2_cache_settings.inc.php` | キャッシュ設定 |
| `tab2_furl_settings.inc.php` | Friendly URL 設定 |
| `tab3_user_settings.inc.php` | ユーザー設定 |
| `tab4_manager_settings.inc.php` | 管理画面設定 |
| `tab6_filemanager_settings.inc.php` | ファイル管理設定 |

## 注意事項

- SQLファイルでの設定追加は不要（`default.config.php` のみで管理）
- `save_settings.processor.php` は POST データと `default.config.php` をマージし `REPLACE INTO` で保存
- 設定値の取得: `evo()->getConfig('setting_name', 'default')`
