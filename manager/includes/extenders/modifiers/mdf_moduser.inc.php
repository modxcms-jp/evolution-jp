<?php
$userid = intval($value);
if (!isset($this->cache['ui'][$userid])) {
    if ($userid < 0) $user = evo()->getWebUserInfo(abs($userid));
    else             $user = evo()->getUserInfo($userid);
    $this->cache['ui'][$userid] = $user;
} else {
    $user = $this->cache['ui'][$userid];
}
if (!is_array($user)) {
    return '';
}
$user['name'] = !empty($user['fullname']) ? $user['fullname'] : ($user['username'] ?? '');

return $user[$opt] ?? '';
