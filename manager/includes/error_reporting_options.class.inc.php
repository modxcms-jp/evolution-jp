<?php

/**
 * エラー報告の段階の一覧と表示名（SSOT）。
 * グローバル設定（error_reporting）は段階だけ、スニペット・プラグインは先頭に「グローバル設定を継承」（inherit）が付く。
 * 言語ファイルの文言は信頼する HTML としてそのまま出す
 */
final class EvoErrorReporting
{
    /** グローバル設定に従う（エレメントの画面だけの選択肢） */
    const INHERIT = 'inherit';

    /** 段階の値 => 言語キー */
    const LEVEL_LANG_KEYS = [
        '0' => 'a17_error_reporting_opt0',
        '1' => 'a17_error_reporting_opt1',
        '2' => 'a17_error_reporting_opt2',
        '99' => 'a17_error_reporting_opt99',
    ];

    /**
     * グローバル設定の選択肢 [値 => 表示名]
     */
    public static function levelOptions(): array
    {
        $options = [];
        foreach (self::LEVEL_LANG_KEYS as $value => $key) {
            $options[$value] = lang($key);
        }
        return $options;
    }

    /**
     * スニペット・プラグインの保存で受け付ける値（先頭は既定値の inherit）
     */
    public static function elementValues(): array
    {
        return array_merge([self::INHERIT], array_map('strval', array_keys(self::LEVEL_LANG_KEYS)));
    }

    /**
     * スニペット・プラグインの編集画面のラジオの組（name は error_reporting）。
     * $current は今の値（inherit・0・1・2・99 の文字列）
     */
    public static function radios(string $current): string
    {
        $labels = [self::INHERIT => lang('element_error_reporting_inherit')] + self::levelOptions();
        $html = [];
        foreach ($labels as $value => $label) {
            $html[] = sprintf(
                "<label>\n<input type=\"radio\" name=\"error_reporting\" value=\"%s\"%s>\n%s\n</label>",
                $value,
                $current === (string)$value ? ' checked="checked"' : '',
                $label
            );
        }
        return implode("<br/>\n", $html);
    }
}
