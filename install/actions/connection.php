<?php

if (sessionv('prevAction') === 'options') {
    sessionv('*installdata', postv('installdata', ''));
    sessionv('*template', postv('template', array()));
    sessionv('*tv', postv('tv', array()));
    sessionv('*chunk', postv('chunk', array()));
    sessionv('*snippet', postv('snippet', array()));
    sessionv('*plugin', postv('plugin', array()));
    sessionv('*module', postv('module', array()));
}
$ph = array_merge(
    $ph
    , array(
        'adminname'         => 'admin',
        'database_server'   => sessionv('database_server','localhost'),
        'table_prefix'      => sessionv('table_prefix','modx_'),
        'is_upgradeable'    => sessionv('is_upgradeable'),
        'adminemail'        => sessionv('adminemail', ''),
        'adminpass'         => sessionv('adminpass', ''),
        'adminpassconfirm'  => sessionv('adminpassconfirm', ''),
        'database_user'     => sessionv('database_user', ''),
        'database_password' => sessionv('database_password', ''),
        'dbase'             => sessionv('dbase', ''),
    )
);
if ($ph['database_server'] === '127.0.0.1') {
    $ph['database_server'] = 'localhost';
}
echo $modx->parseText(
    file_get_contents(
        MODX_BASE_PATH . 'install/tpl/connection.tpl'
    )
    , $ph
);
