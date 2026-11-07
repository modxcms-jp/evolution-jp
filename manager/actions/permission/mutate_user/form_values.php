<?php
/**
 * 保存に失敗したあとの差し戻しで、画面へ戻してよい値だけにする（ユーザー・Web ユーザー・プロフィールの画面で共通）
 *
 * 文字列だけを戻す。配列は、$arrayKeys に挙げた項目の、文字列だけの配列に限る。
 *
 * @param array $values 保存しておいたフォームの値
 * @param string[] $arrayKeys 配列で受けてよい項目名
 * @return array
 */
function restorableFormValues(array $values, array $arrayKeys = [])
{
    return array_filter($values, static function ($v, $k) use ($arrayKeys) {
        return is_string($v)
            || (is_array($v) && in_array($k, $arrayKeys, true) && $v === array_filter($v, 'is_string'));
    }, ARRAY_FILTER_USE_BOTH);
}
