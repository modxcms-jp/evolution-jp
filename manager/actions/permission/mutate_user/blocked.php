<?php
/**
 * ユーザーがブロック中かを判定する（ユーザー・Web ユーザー・プロフィールの画面で共通）
 *
 * 差し戻しの再表示では日時が空文字になることがあるので、比べる前に整数にそろえる。
 *
 * @param array $row blocked / blockeduntil / blockedafter / failedlogins を持つ配列
 * @param bool $withFailedLogins ログイン失敗の回数（4 回以上）もブロック扱いにするか
 */
function isUserBlocked($row, $withFailedLogins = true)
{
    if (!is_array($row)) {
        return false;
    }
    $int = static function ($key) use ($row) {
        return isset($row[$key]) && is_scalar($row[$key]) ? (int)$row[$key] : 0;
    };
    $now = time();
    $until = $int('blockeduntil');
    $after = $int('blockedafter');

    return $int('blocked') === 1
        || ($until !== 0 && $until > $now)
        || ($after !== 0 && $after < $now)
        || ($withFailedLogins && $int('failedlogins') > 3);
}
