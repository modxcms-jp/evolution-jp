<?php

/**
 * このサイトの表（接頭辞の付いた表）の一覧・状態・名前を受け持つ。
 * 表の一覧の問い合わせ（SHOW TABLE STATUS）は、このクラスだけが行う。
 * LIKE の照合では接頭辞の `_`・`%` を文字どおりに扱い、別の接頭辞の表を混ぜない。
 */
final class EvoDbTables
{
    private const NAME_PATTERN = '/\A[0-9A-Za-z_]+\z/';

    /**
     * 接頭辞の付いた表の名前
     */
    public static function names(): array
    {
        return array_column(self::query(), 'Name');
    }

    /**
     * 接頭辞の付いた表の SHOW TABLE STATUS の行。
     * 空にしてよい表の Rows は、見積もりではなく COUNT(*) の数に差し替える（空にするかを決める画面で使うため）
     */
    public static function status(): array
    {
        $truncatable = self::truncatable();
        $rows = self::query();
        foreach ($rows as $i => $row) {
            if (in_array($row['Name'], $truncatable, true)) {
                $rows[$i]['Rows'] = (int) db()->getValue(db()->select('COUNT(*)', '`' . $row['Name'] . '`'));
            }
        }
        return $rows;
    }

    /**
     * 接頭辞を除いた名前（例 'manager_log'）に、必ず接頭辞を付ける。名前に使えない文字があれば ''
     * 接頭辞が付いているかを名前の頭から推し量らない（接頭辞 'manager_' のサイトの 'manager_log' を取り違えないため）
     */
    public static function name(string $short): string
    {
        if (!preg_match(self::NAME_PATTERN, $short)) {
            return '';
        }
        return db()->table_prefix . $short;
    }

    /**
     * 利用者が入力した名前（完全な名前と接頭辞を除いた名前のどちらもありうる）を、このサイトに在る表の名前にする。
     * 完全な名前として在ればそれを、無ければ接頭辞を付けた名前として在ればそれを返す。どちらも無ければ ''
     * いくつも決めるときは、names() の結果を $names に渡すと一覧を引き直さない
     */
    public static function resolve(string $input, ?array $names = null): string
    {
        $input = trim($input);
        if (!preg_match(self::NAME_PATTERN, $input)) {
            return '';
        }
        $names = $names ?? self::names();
        if (in_array($input, $names, true)) {
            return $input;
        }
        $table = self::name($input);
        return in_array($table, $names, true) ? $table : '';
    }

    /**
     * resolve() の表を `db`.`table` の形で返す。表が無いとき、DB 名が無いときは ''
     */
    public static function resolveWithDb(string $input): string
    {
        $table = self::resolve($input);
        $dbase = (string) db()->dbase;
        if ($table === '' || $dbase === '') {
            return '';
        }
        return sprintf('`%s`.`%s`', $dbase, $table);
    }

    /**
     * 表があるか（名前は接頭辞の付いた完全な名前で渡す）
     */
    public static function exists(string $table): bool
    {
        if (!preg_match(self::NAME_PATTERN, $table)) {
            return false;
        }
        $rs = db()->query(sprintf(
            "SHOW TABLES FROM `%s` LIKE '%s'",
            db()->dbase,
            db()->escape(self::likeLiteral($table))
        ));
        if (!$rs) {
            return false;
        }
        $exists = db()->count($rs) > 0;
        db()->freeResult($rs);
        return $exists;
    }

    /**
     * 管理画面から中身を全部消してよい表（接頭辞付き）。
     * 画面のリンクの出し分けと、消す処理の許可の判定が使う。
     * event_log は今は作らないが、古い版から上げたサイトには残っているので、空にできるよう残す
     */
    public static function truncatable(): array
    {
        return [
            self::name('event_log'),
            self::name('manager_log'),
        ];
    }

    /**
     * このサイトの表の名前か（最適化してよい表）
     */
    public static function isOwn(string $name): bool
    {
        return (bool) preg_match(
            '@\A' . preg_quote((string) db()->table_prefix, '@') . '[0-9a-zA-Z_]+\z@',
            $name
        );
    }

    private static function query(): array
    {
        $rs = db()->query(sprintf(
            "SHOW TABLE STATUS FROM `%s` LIKE '%s%%'",
            db()->dbase,
            db()->escape(self::likeLiteral((string) db()->table_prefix))
        ));
        if (!$rs) {
            return [];
        }
        $rows = [];
        while ($row = db()->getRow($rs)) {
            $rows[] = $row;
        }
        db()->freeResult($rs);
        return $rows;
    }

    // LIKE の特別な文字を文字どおりに照合させる
    private static function likeLiteral(string $value): string
    {
        return str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $value);
    }
}
