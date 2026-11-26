<?php

/**
 * パスワードの決まり（長さ・使える文字・推測されやすい値・自動生成の文字集合）と、保存用のハッシュの作り方・照合の置き場所。
 * 管理画面（ex_managerapi の passwordError() / hashPassword()、ログインの照合）、インストーラー、
 * パスワードの欄の JS（clientConfig() を渡す）が、ここだけを見る。決まりと理由は assets/docs/password-policy.md
 *
 * このファイルは管理画面の言語や DB に頼らない（インストーラーからも読む）。エラーは理由の名前で返し、文言は呼び出し側が引く
 */
final class EvoPasswordPolicy
{
    /** 新しく設定するパスワードの最小の文字数（Unicode のコードポイントで数える）。これ未満は保存しない */
    public const MIN_LENGTH = 8;

    /** 推奨の文字数。これ未満は入力中に警告するが、保存は妨げない（強制しない） */
    public const RECOMMENDED_LENGTH = 15;

    /**
     * 最大のバイト数（NFC にそろえた後の UTF-8）。bcrypt は先頭の 72 バイトしか照合しないので、超える値は切り捨てずに断る。
     * 半角の文字だけなら 72 文字まで、全角の文字（3 バイト）だけなら 24 文字まで
     */
    public const MAX_BYTES = 72;

    /** 自動生成の文字数（初期値・スライダーの最小と最大）。手動で入力する値の上限（MAX_BYTES）とは別 */
    public const GENERATE_LENGTH = 20;
    public const GENERATE_MIN = 8;
    public const GENERATE_MAX = 64;

    /**
     * 自動生成の文字集合。小文字は常に使い、大文字・数字・記号は選べる（初期は大文字と数字）。
     * 記号は印字できる半角の記号 32 文字（空白は含めない）。手動で入力する値の文字は制限しない
     */
    public const LOWER = 'abcdefghijklmnopqrstuvwxyz';
    public const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const DIGITS = '0123456789';
    public const SYMBOLS = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~';

    /**
     * パスワードを送るフォームの項目名。入力エラーの差し戻し（ex_managerapi の saveFormValues()）でセッションに残さない。
     * 画面が送るのは userpassword。ほかは使わない名前だが、古い画面や外部のフォームから送られても残さない
     */
    public const FORM_FIELDS = ['userpassword', 'specifiedpassword', 'confirmpassword', 'pass1', 'pass2'];

    /** 繰り返しとみなす単位の最大の長さ（"abcabc…" のような値を断る） */
    private const REPEAT_UNIT_MAX = 4;

    /**
     * 照合とハッシュの前にそろえる。Normalizer（intl）があれば NFC にする。無ければそのまま。
     * 前後の空白を削る・大文字小文字をそろえる、といった加工はしない
     */
    public static function normalize(string $password): string
    {
        if ($password === '' || !class_exists('Normalizer') || !preg_match('/[^\x00-\x7F]/', $password)) {
            return $password;
        }
        $normalized = Normalizer::normalize($password, Normalizer::FORM_C);
        return is_string($normalized) ? $normalized : $password;
    }

    /** 文字数（Unicode のコードポイントの数）。正しい UTF-8 でなければ -1 */
    public static function length(string $password): int
    {
        if (!preg_match('//u', $password)) {
            return -1;
        }
        return (int)preg_match_all('/./us', $password);
    }

    /**
     * 新しく設定するパスワードを確かめる。
     *
     * @param string $password 送られたままの値
     * @param string[] $context 推測されやすい値（ログイン名・メールアドレス・氏名・サイト名など）。全体が一致する値を断る
     * @return string 問題がなければ ''。あれば理由の名前:
     *                invalid（正しい UTF-8 でない・制御文字を含む）/ too_short / too_long /
     *                context（ログイン名などと同じ）/ common（単純な並び）。
     *                頻出・漏えい済みのパスワードの一覧（ブロックリスト）との照合はまだ無い（assets/docs/password-policy.md）
     */
    public static function check(string $password, array $context = []): string
    {
        $password = self::normalize($password);
        $length = self::length($password);
        if ($length < 0 || preg_match('/[\x00-\x1F\x7F]|\xC2[\x80-\x9F]/', $password)) {
            return 'invalid';
        }
        if ($length < self::MIN_LENGTH) {
            return 'too_short';
        }
        if (strlen($password) > self::MAX_BYTES) {
            return 'too_long';
        }

        $key = self::compareKey($password);
        foreach (self::contextValues($context) as $value) {
            if ($key === $value) {
                return 'context';
            }
        }
        if (self::isSimplePattern($key)) {
            return 'common';
        }
        return '';
    }

    /** 保存用のハッシュ（ソルトとコストは password_hash() が値に含める） */
    public static function hash(string $password): string
    {
        return password_hash(self::normalize($password), PASSWORD_DEFAULT);
    }

    /**
     * password_hash() のハッシュと照合する。
     * NFC にそろえた値で照合し、合わなければ送られたままの値でも照合する（正規化を入れる前に、全角の文字を含む値で保存したユーザーのため）。
     *
     * @return array{0: bool, 1: bool} [合ったか, 作り直すか（コストの変更・そろえていない値で保存されていたとき）]
     */
    public static function verify(string $password, string $hash): array
    {
        $normalized = self::normalize($password);
        if (password_verify($normalized, $hash)) {
            return [true, password_needs_rehash($hash, PASSWORD_DEFAULT)];
        }
        if ($normalized !== $password && password_verify($password, $hash)) {
            return [true, true];
        }
        return [false, false];
    }

    /** パスワードの欄の JS に渡す決まり（文字数・文字集合）。js_value() で埋め込む */
    public static function clientConfig(): array
    {
        return [
            'minLength' => self::MIN_LENGTH,
            'recommendedLength' => self::RECOMMENDED_LENGTH,
            'maxBytes' => self::MAX_BYTES,
            'generateLength' => self::GENERATE_LENGTH,
            'generateMin' => self::GENERATE_MIN,
            'generateMax' => self::GENERATE_MAX,
            'lower' => self::LOWER,
            'upper' => self::UPPER,
            'digits' => self::DIGITS,
            'symbols' => self::SYMBOLS,
        ];
    }

    /** 推測されやすい値と比べるための形。英字の大文字だけを小文字にする（ロケールに左右されないよう strtolower() は使わない） */
    private static function compareKey(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /** 推測されやすい値と、メールアドレスの @ の前 */
    private static function contextValues(array $context): array
    {
        $values = [];
        foreach ($context as $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }
            $values[] = self::compareKey(self::normalize($value));
            $at = strpos($value, '@');
            if ($at > 0) {
                $values[] = self::compareKey(self::normalize(substr($value, 0, $at)));
            }
        }
        return $values;
    }

    /**
     * 全体が単純な並びか。短い単位の繰り返し（aaaa…・abcabc…・1212…）と、1 つずつ増える・減る並び（abcdef…・987654…・数字は 9 の次を 0 とする）
     */
    private static function isSimplePattern(string $key): bool
    {
        $chars = preg_split('//u', $key, -1, PREG_SPLIT_NO_EMPTY);
        $count = count($chars);
        for ($unit = 1; $unit <= self::REPEAT_UNIT_MAX; $unit++) {
            $repeated = true;
            for ($i = $unit; $i < $count; $i++) {
                if ($chars[$i] !== $chars[$i - $unit]) {
                    $repeated = false;
                    break;
                }
            }
            if ($repeated) {
                return true;
            }
        }
        foreach ([1, -1] as $step) {
            $sequential = true;
            for ($i = 1; $i < $count; $i++) {
                if (!self::isNextChar($chars[$i - 1], $chars[$i], $step)) {
                    $sequential = false;
                    break;
                }
            }
            if ($sequential) {
                return true;
            }
        }
        return false;
    }

    private static function isNextChar(string $prev, string $current, int $step): bool
    {
        if (ctype_digit($prev) && ctype_digit($current)) {
            return ((int)$prev + $step + 10) % 10 === (int)$current;
        }
        if (strlen($prev) !== 1 || strlen($current) !== 1) {
            return false;
        }
        return ord($prev) + $step === ord($current);
    }
}
