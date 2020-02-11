<?php

if(sessionv('prevAction')==='options') {
    $_SESSION['installdata'] = postv('installdata', '');
    $_SESSION['template']    = postv('template', array());
    $_SESSION['tv']          = postv('tv', array());
    $_SESSION['chunk']       = postv('chunk', array());
    $_SESSION['snippet']     = postv('snippet', array());
    $_SESSION['plugin']      = postv('plugin', array());
    $_SESSION['module']      = postv('module', array());
}

$ph = array(
    'adminname'         => 'admin',
    'database_server'   => 'localhost',
    'table_prefix'      => 'modx_',
    'is_upgradeable'    => sessionv('is_upgradeable'),
    'adminemail'        => sessionv('adminemail', ''),
    'adminpass'         => sessionv('adminpass', ''),
    'adminpassconfirm'  => sessionv('adminpassconfirm', ''),
    'database_user'     => sessionv('database_user', ''),
    'database_password' => sessionv('database_password', ''),
    'dbase'             => sessionv('adminemail', ''),
);
if($ph['database_server'] === '127.0.0.1') {
    $ph['database_server'] = 'localhost';
}
echo  $modx->parseText(
    file_get_contents(
        MODX_BASE_PATH . 'install/tpl/connection.tpl'
    )
    , $ph
);
