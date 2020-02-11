<?php

if($_SESSION['prevAction']==='options') {
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
    'installmode'       => evo()->session('installmode'),
    'adminemail'        => evo()->session('adminemail', ''),
    'adminpass'         => evo()->session('adminpass', ''),
    'adminpassconfirm'  => evo()->session('adminpassconfirm', ''),
    'database_user'     => evo()->session('database_user', ''),
    'database_password' => evo()->session('database_password', ''),
    'dbase'             => evo()->session('adminemail', ''),
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
