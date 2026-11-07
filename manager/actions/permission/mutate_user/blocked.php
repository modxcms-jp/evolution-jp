<?php
/**
 * ユーザーがブロック中かを判定する（ユーザー・Web ユーザー・プロフィールの画面で共通）
 *
 * 日時はタイムスタンプで受ける（差し戻しの値は、呼び出し側で ConvertDate() で戻す）。空文字などは整数にそろえて 0 として扱う。
 *
 * @param array $row blocked / blockeduntil / blockedafter を持つ配列
 */
function isUserBlocked($row)
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
        || ($after !== 0 && $after < $now);
}
