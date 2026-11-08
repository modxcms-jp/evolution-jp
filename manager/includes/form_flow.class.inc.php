<?php

/**
 * 管理画面の編集画面の「戻り先」を決める 1 か所。
 * 保存したあとどの画面へ戻るか、入力エラーでどの画面へ差し戻すかを、画面の種類ごとの表（SCREENS）から決める。
 * processor が画面の番号（a）を書き写さないための部品。
 *
 * 引数の名前と値（ここを正とする）:
 * - after_save: 保存後の行き先。new（続けて新規作成）/ stay（この画面＝続けて編集）/ close（閉じる）
 * - mode: 新規か更新か。new / edit（ユーザーのプロフィールだけ profile）
 * - refresh: 保存後に読み直すもの
 *
 * refresh は呼び出し側が $extra で渡す。
 *
 * ChildDocumentListState と同じく、状態を持たない静的クラス。
 * URL はエスケープ前の文字列で返す（HTML に出すときは呼び出し側で hsc() する）。
 */
final class EvoFormFlow
{
    /**
     * 編集画面の種類ごとの画面の番号。new: 新規、edit: 更新、profile: プロフィール、close: 閉じたあとの一覧
     */
    private const SCREENS = [
        'chunk' => ['new' => 77, 'edit' => 78, 'close' => 76],
        'snippet' => ['new' => 23, 'edit' => 22, 'close' => 76],
        'template' => ['new' => 19, 'edit' => 16, 'close' => 76],
        'tmplvar' => ['new' => 300, 'edit' => 301, 'close' => 76],
        'plugin' => ['new' => 101, 'edit' => 102, 'close' => 76],
        'module' => ['new' => 107, 'edit' => 108, 'close' => 106],
        'role' => ['new' => 38, 'edit' => 35, 'close' => 86],
        'user' => ['new' => 11, 'edit' => 12, 'profile' => 74, 'close' => 75],
        'web_user' => ['new' => 87, 'edit' => 88, 'close' => 99],
        'document' => ['new' => 4, 'edit' => 27],
        'weblink' => ['new' => 72, 'edit' => 27],
    ];

    /** 保存後の行き先として受ける値 */
    private const AFTER_SAVE = ['new', 'stay', 'close'];

    /**
     * 種類の表を返す。表に無い種類は書き間違いなので、その場で止める
     *
     * @return int[] mode => 画面の番号
     */
    private static function screens(string $kind): array
    {
        if (!isset(self::SCREENS[$kind])) {
            throw new InvalidArgumentException("Unknown form kind: {$kind}");
        }
        return self::SCREENS[$kind];
    }

    /**
     * 種類と mode の画面の番号。mode が空文字か close なら close の番号。
     * 表に無い mode と、close の無い種類（文書）の close は InvalidArgumentException
     */
    public static function screen(string $kind, string $mode): int
    {
        $screens = self::screens($kind);
        if ($mode === '') {
            $mode = 'close';
        }
        if (!isset($screens[$mode])) {
            throw new InvalidArgumentException("Unknown form mode: {$kind}/{$mode}");
        }
        return $screens[$mode];
    }

    /** index.php への URL。エスケープ前の文字列 */
    public static function url(array $query): string
    {
        return 'index.php?' . http_build_query($query, '', '&');
    }

    /**
     * POST の mode を、その種類の表にある new・edit・profile だけ受ける。
     * それ以外（送られていない・許可していない値）は空文字
     */
    public static function postedMode(string $kind): string
    {
        return post_choicevar('mode', self::modes($kind), '');
    }

    /**
     * 画面側の mode。今開いている画面（manager()->action）が、その種類の表のどの値か。
     * フォームの hidden の mode に出す。どれでもなければ空文字
     */
    public static function mode(string $kind): string
    {
        $mode = array_search((int)manager()->action, self::screens($kind), true);
        return $mode === false || $mode === 'close' ? '' : $mode;
    }

    /**
     * その種類で mode として受ける値（close を除く）
     *
     * @return string[]
     */
    private static function modes(string $kind): array
    {
        return array_values(array_diff(array_keys(self::screens($kind)), ['close']));
    }

    /**
     * POST の保存後の行き先。new / stay / close と $extra のどれか（許可していない値・無いときは close）
     *
     * @param string[] $extra 文書の下書きなど、種類だけが追加で受ける値
     */
    public static function postedAfterSave(array $extra = []): string
    {
        return post_choicevar('after_save', array_merge(self::AFTER_SAVE, $extra), 'close');
    }

    /**
     * 画面の選択欄の既定。GET（保存後の戻り）か POST（文書の再表示）の after_save。
     * 許可していない値・無いときは空文字
     *
     * @param string[] $extra 文書の下書きなど、種類だけが追加で受ける値
     */
    public static function requestedAfterSave(array $extra = []): string
    {
        return request_choicevar('after_save', array_merge(self::AFTER_SAVE, $extra), '');
    }

    /**
     * 編集画面のタブを開いたときに復元するか。
     * 設定 remember_last_tab が 2 ならいつも、1 なら「この画面」で戻ったとき（GET の after_save=stay）だけ
     */
    public static function rememberTab(): bool
    {
        $setting = (int)config('remember_last_tab');
        return $setting === 2
            || ($setting === 1 && get_choicevar('after_save', ['stay'], '') === 'stay');
    }

    /**
     * 選択欄の option の HTML（エスケープ済み）。順は new（$withNew のときだけ）・stay・$extra・close。
     * $selected が出した選択肢に無ければ close を選ぶ
     *
     * @param bool $withNew 「次の作成」を出すか
     * @param string[] $extra 値 => 文言。文書の下書きなど、種類だけが追加で出す選択肢
     */
    public static function afterSaveOptions(string $selected, bool $withNew, array $extra = []): string
    {
        $options = [];
        if ($withNew) {
            $options['new'] = lang('stay_new');
        }
        $options['stay'] = lang('stay');
        $options += $extra;
        $options['close'] = lang('close');
        if (!isset($options[$selected])) {
            $selected = 'close';
        }

        $html = [];
        foreach ($options as $value => $label) {
            $html[] = sprintf(
                '<option value="%s"%s>%s</option>',
                hsc($value),
                (string)$value === $selected ? ' selected="selected"' : '',
                hsc($label)
            );
        }
        return implode("\n", $html);
    }

    /** 文書の選択欄の既定。セッションに記録した stay か close。それ以外・無いときは close */
    public static function documentAfterSaveDefault(): string
    {
        $afterSave = sessionv('saveAfter', 'close');
        return in_array($afterSave, ['stay', 'close'], true) ? $afterSave : 'close';
    }

    /** 文書の保存後の選択を記録する。new は記録しない（前の選択を保つ） */
    public static function rememberDocumentAfterSave(string $afterSave): void
    {
        if ($afterSave !== 'new') {
            sessionv('*saveAfter', $afterSave);
        }
    }

    /**
     * 保存後の URL。new は新規の画面、stay は編集の画面（id 付き）、それ以外は close の画面。
     * $extra（refresh など）はどの場合にも付ける。並びは a・id・$extra・after_save
     *
     * @param array $extra 足す引数（キー => 値）
     */
    public static function afterSaveUrl(string $kind, int $id, string $afterSave, array $extra = []): string
    {
        if ($afterSave === 'stay') {
            $query = ['a' => self::screen($kind, 'edit'), 'id' => $id];
        } elseif ($afterSave === 'new') {
            $query = ['a' => self::screen($kind, 'new')];
        } else {
            return self::url(['a' => self::screen($kind, 'close')] + $extra);
        }
        return self::url($query + $extra + ['after_save' => $afterSave]);
    }

    /**
     * 入力エラーの差し戻し先の URL。mode が edit なら id を付ける。
     * mode が空なら close の画面へ戻す。行き先が close でなければ、どの場合にも引き継ぐ
     * （close を付けないのは、戻った画面の選択欄の既定が「閉じる」だから）
     */
    public static function formUrl(string $kind, string $mode, int $id, string $afterSave): string
    {
        $query = ['a' => self::screen($kind, $mode)];
        if ($mode === 'edit') {
            $query['id'] = $id;
        }
        if ($afterSave !== 'close') {
            $query['after_save'] = $afterSave;
        }
        return self::url($query);
    }
}
