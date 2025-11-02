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
$getEnv = static function (string $key, $default = null) {
    if (function_exists('env')) {
        $value = env($key, $default);
    } else {
        $value = getenv($key);
        if ($value === false) {
            $value = $default;
        }
    }

    return ($value === null || $value === false || $value === '') ? $default : $value;
};

$ph = array_merge(
    $ph,
    [
        'adminname'         => 'admin',
        'database_server'   => sessionv('database_server', $getEnv('DB_HOST', 'localhost')),
        'table_prefix'      => sessionv('table_prefix', $getEnv('TABLE_PREFIX', 'modx_')),
        'is_upgradeable'    => sessionv('is_upgradeable'),
        'adminemail'        => sessionv('adminemail', ''),
        'adminpass'         => sessionv('adminpass', ''),
        'adminpassconfirm'  => sessionv('adminpassconfirm', ''),
        'database_user'     => sessionv('database_user', $getEnv('DB_USERNAME', '')),
        'database_password' => sessionv('database_password', $getEnv('DB_PASSWORD', '')),
        'dbase'             => sessionv('dbase', $getEnv('DB_DATABASE', '')),
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
