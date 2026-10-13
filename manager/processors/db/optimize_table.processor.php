<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!hasPermission('settings') || (!hasPermission('logs') && !hasPermission('bk_manager'))) {
    alert()->setError(3);
    alert()->dumpError();
}

if (!anyv('t') && !anyv('u')) {
    alert()->setError(10);
    alert()->dumpError();
}

// 画面のリンクに頼らず、対象テーブル名をサーバー側でも検証する。
// truncate（中身の全削除）は破壊的なので、画面で許可しているテーブルだけに厳格制限する。
$truncateable = [
    db()->table_prefix . 'event_log',
    db()->table_prefix . 'manager_log',
];

if (anyv('t')) {
    // optimize（最適化）は非破壊だが、他DBや無関係なテーブルを触らせないよう
    // 自サイトの接頭辞を持つテーブル名に限定する
    $t = (string) anyv('t');
    if (preg_match('@^' . preg_quote(db()->table_prefix, '@') . '[0-9a-zA-Z_]+$@', $t)) {
        db()->optimize($t);
    } else {
        alert()->setError(3);
        alert()->dumpError();
    }
}
if (anyv('u')) {
    $u = (string) anyv('u');
    if (in_array($u, $truncateable, true)) {
        db()->truncate($u);
    } else {
        alert()->setError(3);
        alert()->dumpError();
    }
}

header('Location: index.php?a=' . (int)anyv('mode') . '&s=4');
