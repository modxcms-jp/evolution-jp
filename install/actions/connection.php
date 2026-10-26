<?php

if (sessionv('prevAction') === 'options') {
    sessionv('*installdata', postv('installdata', ''));
    sessionv('*template', postv('template', []));
    sessionv('*tv', postv('tv', []));
    sessionv('*chunk', postv('chunk', []));
    sessionv('*snippet', postv('snippet', []));
    sessionv('*plugin', postv('plugin', []));
    sessionv('*module', postv('module', []));
}

$ph = array_merge(
    $ph, [
        'adminname'         => 'admin',
        'database_server'   => sessionv('database_server') ?: env('DB_HOST') ?: 'localhost',
        'table_prefix'      => sessionv('table_prefix') ?: env('TABLE_PREFIX') ?: 'modx_',
        'is_upgradeable'    => sessionv('is_upgradeable'),
        'adminemail'        => sessionv('adminemail') ?: '',
        'adminpass'         => sessionv('adminpass') ?: '',
        'adminpassconfirm'  => sessionv('adminpassconfirm') ?: '',
        'database_user'     => sessionv('database_user') ?: env('DB_USERNAME') ?: '',
        // 保存済みのパスワードは画面に出さない（毎回入力してもらう）
        'database_password' => '',
        'dbase'             => sessionv('dbase') ?: env('DB_DATABASE') ?: '',
    ]
);
if ($ph['database_server'] === '127.0.0.1') {
    $ph['database_server'] = 'localhost';
}
echo $modx->parseText(
    file_get_contents(
        MODX_BASE_PATH . 'install/tpl/connection.tpl'
    ),
    $ph
);
