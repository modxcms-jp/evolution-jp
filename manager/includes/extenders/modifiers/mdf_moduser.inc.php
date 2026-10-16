<?php
$userid = intval($value);
if (!isset($modx->filter->cache['ui'][$userid])) {
    if ($userid < 0) $user = $modx->getWebUserInfo(abs($userid));
    else             $user = evo()->getUserInfo($userid);
    $modx->filter->cache['ui'][$userid] = $user;
} else {
    $user = $modx->filter->cache['ui'][$userid];
}
if (!is_array($user)) {
    return '';
}
$user['name'] = !empty($user['fullname']) ? $user['fullname'] : ($user['username'] ?? '');

return $user[$opt] ?? '';
