<?php

/**
 * エラー報告の段階の一覧・表示名・エラーのマスク（SSOT）。
 * グローバル設定（error_reporting）は段階だけ、スニペット・プラグインは先頭に「グローバル設定を継承」（inherit）が付く。
 * 公開側（DocumentParser）も読むので、言語ファイルを使うのは表示名のメソッド（levelOptions・radios）だけにする
 */
final class EvoErrorReporting
{
    /** グローバル設定に従う（エレメントの画面だけの選択肢） */
    const INHERIT = 'inherit';

    /** グローバル設定が未設定・不正なときの段階 */
    const DEFAULT_LEVEL = '1';

    /**
     * 段階 => 言語キー。キーは PHP の配列キーとして整数になるので、値として使うときは文字にする
     */
    const LEVEL_LANG_KEYS = [
        0 => 'a17_error_reporting_opt0',
        1 => 'a17_error_reporting_opt1',
        2 => 'a17_error_reporting_opt2',
        99 => 'a17_error_reporting_opt99',
    ];

    /**
     * 段階の値の一覧（文字列）
     */
    public static function levels(): array
    {
        return array_map('strval', array_keys(self::LEVEL_LANG_KEYS));
    }

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
        return array_merge([self::INHERIT], self::levels());
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

    /**
     * 実行するエレメントの段階を決める。許さない値は継承として扱い、継承はグローバル設定の値（不正なら既定）に従う
     *
     * @param mixed  $requested     エレメントの error_reporting の値
     * @param string $globalSetting グローバル設定の error_reporting
     */
    public static function resolve($requested, string $globalSetting): array
    {
        $valid = self::levels();
        if (!in_array($globalSetting, $valid, true)) {
            $globalSetting = self::DEFAULT_LEVEL;
        }

        if ($requested === null || $requested === '') {
            $requested = self::INHERIT;
        }

        $requestedValue = (string)$requested;
        if ($requestedValue !== self::INHERIT && !in_array($requestedValue, $valid, true)) {
            $requestedValue = self::INHERIT;
        }

        $effective = $requestedValue === self::INHERIT ? $globalSetting : $requestedValue;
        $level = (int)$effective;

        return [
            'requested' => $requestedValue,
            'effective' => $effective,
            'level' => $level,
            'mask' => self::mask($level),
            'global' => $globalSetting,
            'compatibility' => $level !== 99,
        ];
    }

    /**
     * 段階から PHP のエラーのマスクを決める
     */
    public static function mask(int $level): int
    {
        switch ($level) {
            case 0:
                return 0;
            case 1:
                return E_ALL & ~E_NOTICE & ~E_DEPRECATED;
            case 2:
                return E_ALL & ~E_NOTICE;
            default:
                return E_ALL;
        }
    }
}
