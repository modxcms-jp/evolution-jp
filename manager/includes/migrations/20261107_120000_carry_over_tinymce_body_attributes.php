<?php

/**
 * 旧 TinyMCE のプロパティ「Custom Parameters」（customparams）にある body_id・body_class を、
 * TinyMCE 7 の設定（tinymce7_body_id・tinymce7_body_class）へ引き継ぐ。
 *
 * customparams は tinyMCE.init({...}) に差し込む JS の断片（例: body_id:'center',）で、TinyMCE 7 には同じ欄が無い。
 * 旧プラグインは TinyMCE 7 の @legacy_names で止まるだけで、プロパティは引き継がれない。
 * 引き継げない値（ほかの設定、リソースごとに変わる [*...*] を含む値）は、キー名だけを警告としてログに残す。
 * どちらかの設定が既に保存されていれば、利用者が決めた値として触らない。
 */
return static function (EvoMigrationHelper $m) {
    if (!$m->tableExists('site_plugins')) {
        return;
    }
    $settings = ['body_id' => 'tinymce7_body_id', 'body_class' => 'tinymce7_body_class'];
    foreach ($settings as $setting) {
        if ($m->hasSetting($setting)) {
            return;
        }
    }

    // assets/plugins/tinymce7/tinymce.install_base.tpl の @legacy_names と同じ
    $legacyNames = ['TinyMCE Rich Text Editor', 'TinyMCE'];
    $plugins = $m->rows(
        sprintf(
            "SELECT `name`, `properties` FROM %s WHERE `name` IN ('%s') ORDER BY `disabled` ASC, `id` DESC LIMIT 1",
            $m->table('site_plugins'),
            implode("','", db()->escape($legacyNames))
        ),
        'SELECT the old TinyMCE plugin'
    );
    if (!$plugins) {
        return;
    }
    $customParams = trim((string)(evo()->parseProperties($plugins[0]['properties'])['customparams'] ?? ''));
    if ($customParams === '') {
        return;
    }

    $carried = [];
    $skipped = [];
    $rest = preg_replace_callback(
        '/(?<![\w$])(["\']?)(body_id|body_class)\1\s*:\s*(["\'])(.*?)(?<!\\\\)\3\s*,?/s',
        static function ($match) use ($m, $settings, &$carried, &$skipped) {
            $value = trim(stripslashes($match[4]));
            if ($value === '' || strpos($value, '[*') !== false) {
                $skipped[] = $match[2];
                return '';
            }
            $m->saveSetting($settings[$match[2]], $value);
            $carried[] = $settings[$match[2]];
            return '';
        },
        $customParams
    );

    if ($carried) {
        $m->log('Carried over the body attributes from Custom Parameters of the old TinyMCE plugin', [
            'plugin' => $plugins[0]['name'],
            'settings' => $carried,
        ]);
    }

    if (trim((string)$rest, " \t\r\n,") !== '') {
        preg_match_all('/(?<![\w$])(["\']?)([A-Za-z_$][\w$]*)\1\s*:/', (string)$rest, $keys);
        $skipped = array_merge($skipped, $keys[2]);
    }
    if ($skipped) {
        $m->log('Did not carry over some Custom Parameters of the old TinyMCE plugin', [
            'plugin' => $plugins[0]['name'],
            'keys' => array_values(array_unique($skipped)),
        ], 2);
    }
};
