<?php

    return ModUser($userid,$field);

    function ModUser($userid,$field) {
        global $modx;
        if (!isset($modx->filter->cache['ui']) || !array_key_exists($userid, $modx->filter->cache['ui'])) {
            if (intval($userid) < 0) {
                $user = $modx->getWebUserInfo(-($userid));
            } else {
                $user = $modx->getUserInfo($userid);
            }
            $modx->filter->cache['ui'][$userid] = $user;
        } else {
            $user = $modx->filter->cache['ui'][$userid];
        }
        $user['name'] = !empty($user['fullname']) ? $user['fullname'] : $user['fullname'];
        
        return $user[$field];
    }
