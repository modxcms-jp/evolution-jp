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

    // 文字列とコメントを左から順に取り出して目印（\x01番号\x01）に置き換え、値の中身（例: 'p{color:red}'）を
    // キーや区切りと取り違えないようにする。閉じていない文字列（値の ; で切れたものなど）も取り出す
    $strings = [];
    $code = preg_replace_callback(
        '~//[^\n]*|/\*.*?(?:\*/|$)|(["\'])((?:\\\\.|(?!\1).)*)(\1?)~s',
        static function ($match) use (&$strings) {
            if (!isset($match[1]) || $match[1] === '') {
                return ' ';
            }
            $strings[] = ['value' => stripslashes($match[2]), 'closed' => $match[3] !== ''];
            return "\x01" . (count($strings) - 1) . "\x01";
        },
        $customParams
    );
    // キーは名前か、名前の形をした引用符付きの文字列
    $keyName = static function ($name, $index) use ($strings) {
        $name = $name !== '' ? $name : $strings[(int)$index]['value'];
        return preg_match('/\A[A-Za-z_$][\w$]*\z/', $name) ? $name : '';
    };
    // body_id・body_class に使える値。引用符・改行・: と、サイトのタグ（[* [( [+ [! [[ {{）を含むものは引き継がない
    $isPlainValue = static function (array $string) {
        $value = trim($string['value']);
        return $string['closed'] && $value !== ''
            && !preg_match('/["\'\\\\:\r\n]|\[[*(+!\[]|\{\{/', $value);
    };

    $carried = [];
    $skipped = [];
    // 「キー: 文字列」のあとに , か終わりが続くもの（'a' + 'b' のような式は除く）
    $rest = preg_replace_callback(
        '/(?<![\w$])(?:([A-Za-z_$][\w$]*)|\x01(\d+)\x01)\s*:\s*\x01(\d+)\x01\s*(?:,|$)/',
        static function ($match) use ($m, $settings, $strings, $keyName, $isPlainValue, &$carried, &$skipped) {
            $key = $keyName($match[1], $match[2]);
            if (!isset($settings[$key])) {
                return $match[0];
            }
            $value = $strings[(int)$match[3]];
            if (!$isPlainValue($value)) {
                $skipped[] = $key;
                return ' ';
            }
            $m->saveSetting($settings[$key], trim($value['value']));
            $carried[] = $settings[$key];
            return ' ';
        },
        (string)$code
    );

    if ($carried) {
        $m->log('Carried over the body attributes from Custom Parameters of the old TinyMCE plugin', [
            'plugin' => $plugins[0]['name'],
            'settings' => array_values(array_unique($carried)),
        ]);
    }

    preg_match_all('/(?<![\w$])(?:([A-Za-z_$][\w$]*)|\x01(\d+)\x01)\s*:/', (string)$rest, $keys, PREG_SET_ORDER);
    foreach ($keys as $key) {
        $name = $keyName($key[1], $key[2] ?? '');
        if ($name !== '') {
            $skipped[] = $name;
        }
    }
    if ($skipped) {
        $m->log('Did not carry over some Custom Parameters of the old TinyMCE plugin', [
            'plugin' => $plugins[0]['name'],
            'keys' => array_values(array_unique($skipped)),
        ], 2);
    }
};
