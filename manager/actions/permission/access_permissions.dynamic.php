<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('access_permissions')) {
    alert()->setError(3);
    alert()->dumpError();
}

$access = [
    'action' => 41,
    'title' => 'mgr_access_permissions',
    'groups_tab' => 'access_permissions_user_groups',
    'users_in' => 'access_permissions_users_in_group',
    'group_names' => 'membergroup_names',
    'members' => ['member_groups', 'user_group', 'member'],
    'users' => 'manager_users',
    'user_action' => 12,
    'access' => ['membergroup_access', 'membergroup'],
];
include __DIR__ . '/access_permissions.inc.php';
