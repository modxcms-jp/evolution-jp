<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!hasPermission('settings') || (!hasPermission('logs') && !hasPermission('bk_manager'))) {
    alert()->setError(3);
    alert()->dumpError();
}

$table = request_strvar('table');
$truncateTarget = request_strvar('truncate');
if (!$table && !$truncateTarget) {
    alert()->setError(10);
    alert()->dumpError();
}

// 画面のリンクに頼らず、対象テーブル名をサーバー側でも検証する。
// truncate（中身の全削除）は破壊的なので、画面で許可しているテーブルだけに厳格制限する。
$truncateable = db()->truncateableTables();

if ($table) {
    // optimize（最適化）は非破壊だが、他DBや無関係なテーブルを触らせないよう
    // 自サイトの接頭辞を持つテーブル名に限定する
    $t = $table;
    if (preg_match('@^' . preg_quote(db()->table_prefix, '@') . '[0-9a-zA-Z_]+$@', $t)) {
        db()->optimize($t);
    } else {
        alert()->setError(3);
        alert()->dumpError();
    }
}
if ($truncateTarget) {
    $u = $truncateTarget;
    if (in_array($u, $truncateable, true)) {
        db()->truncate($u);
    } else {
        alert()->setError(3);
        alert()->dumpError();
    }
}

// 戻り先は画面の名前で受け、画面の番号に直す（無ければダッシュボード）
$returnScreens = [
    'sysinfo' => 53,
    'bkmanager' => 93,
];
$from = request_choicevar('from', array_keys($returnScreens), '');
header('Location: index.php?a=' . ($returnScreens[$from] ?? 2));
