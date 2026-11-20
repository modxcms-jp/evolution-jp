<?php

/**
 * 管理画面のフォームの行（見出しのセルと値のセルを持つ tr）を描く部品。
 * 使い方と決まりは assets/docs/manager-form-row.md
 */
final class EvoFieldRow
{
    /** @var string|null 読み込んだ tpl */
    private static $tpl = null;

    /**
     * @param array $row field（data-field の値。フィールドでない行は空）/ label（見出しの HTML）/
     *                   label_extra（見出しのセルで名前の後ろに置く HTML）/ body（値のセルの HTML）/
     *                   help（値の下の説明の HTML。空なら出さない）/ class（行に足す class）/
     *                   attr（行に足す属性 [名前 => 値]。値は hsc() する）
     *                   HTML を受けるキーは、呼び出し側で出力に合わせてエスケープしてから渡す。
     *                   差し込んだ値は再評価しない（値の中の [+…+] はそのまま出る）
     */
    public static function render(array $row): string
    {
        $attr = '';
        foreach ((array)($row['attr'] ?? []) as $name => $value) {
            $attr .= sprintf(' %s="%s"', $name, hsc($value));
        }
        $help = (string)($row['help'] ?? '');

        return strtr(self::tpl(), [
            '[+field+]' => hsc($row['field'] ?? ''),
            '[+label+]' => $row['label'] ?? '',
            '[+label_extra+]' => $row['label_extra'] ?? '',
            '[+body+]' => $row['body'] ?? '',
            '[+help+]' => $help !== '' ? '<div class="form-text" data-field-part="help">' . $help . '</div>' : '',
            '[+class+]' => !empty($row['class']) ? ' ' . hsc($row['class']) : '',
            '[+attr+]' => $attr,
        ]);
    }

    private static function tpl(): string
    {
        if (self::$tpl !== null) {
            return self::$tpl;
        }
        $dir = (string)config('custom_tpl_dir');
        $custom = $dir !== '' ? MODX_BASE_PATH . $dir . 'field_row.tpl' : '';
        self::$tpl = file_get_contents(
            $custom !== '' && is_file($custom) ? $custom : MODX_MANAGER_PATH . 'media/style/common/field_row.tpl'
        );
        return self::$tpl;
    }
}
