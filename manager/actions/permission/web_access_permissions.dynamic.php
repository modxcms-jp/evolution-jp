<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('web_access_permissions') || config('use_udperms') == 0) {
    alert()->setError(3);
    alert()->dumpError();
}

$access = [
    'action' => 92,
    'title' => 'web_access_permissions',
    'groups_tab' => 'web_access_permissions_user_groups',
    'users_in' => 'web_access_permissions_users_in_group',
    'group_names' => 'webgroup_names',
    'members' => ['web_groups', 'webgroup', 'webuser'],
    'users' => 'web_users',
    'user_action' => 88,
    'access' => ['webgroup_access', 'webgroup'],
];
include __DIR__ . '/access_permissions.inc.php';
