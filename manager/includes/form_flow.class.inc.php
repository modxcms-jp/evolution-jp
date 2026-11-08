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
 * 移行中の注記: after_save と mode の外に出す名前と値は、まだ旧い形のまま
 * （行き先はキー stay の 1（new）/ 2（stay）、mode は画面の番号）。内部の分岐は名前で行い、
 * 画面とのやり取りの境目（postedMode() / postedAfterSave() / afterSaveUrl() / formUrl()）でだけ読み替える。
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

    /** 行き先を画面へ出す旧い形（キー stay の値）。移行の間だけ使う */
    private const LEGACY_STAY = ['new' => 1, 'stay' => 2];

    /**
     * 種類と mode の画面の番号。mode が new・edit・profile 以外（空を含む）なら close の番号。
     * close の無い種類（文書）で close を求めたら 0
     */
    public static function screen(string $kind, string $mode): int
    {
        $screens = self::SCREENS[$kind] ?? [];
        return $screens[$mode] ?? $screens['close'] ?? 0;
    }

    /** index.php への URL。エスケープ前の文字列 */
    public static function url(array $query): string
    {
        return 'index.php?' . http_build_query($query, '', '&');
    }

    /**
     * POST の mode を、その種類の表にある new・edit・profile だけ受けて名前にする。
     * それ以外（送られていない・許可していない値）は空文字。
     * 今は画面の番号で送られてくるので、番号を名前に直す
     */
    public static function postedMode(string $kind): string
    {
        $modes = [];
        foreach (self::SCREENS[$kind] ?? [] as $mode => $number) {
            if ($mode !== 'close') {
                $modes[$number] = $mode;
            }
        }
        $number = post_choicevar('mode', array_keys($modes), 0);
        return $modes[$number] ?? '';
    }

    /**
     * POST の保存後の行き先。new / stay / close のどれか（許可していない値・無いときは close）。
     * 今はキー stay の 1（new）/ 2（stay）で送られてくるので、名前に直す。
     *
     * @param string[] $extra 文書の下書きなど、種類だけが追加で受ける値。移行中は未使用（文書は M2 から使う）
     */
    public static function postedAfterSave(array $extra = []): string
    {
        $stay = post_choicevar('stay', array_values(self::LEGACY_STAY), 0);
        return array_search($stay, self::LEGACY_STAY, true) ?: 'close';
    }

    /**
     * 保存後の URL。new は新規の画面、stay は編集の画面（id 付き）、それ以外は close の画面。
     * $extra（refresh など）はどの場合にも付ける。並びは a・id・$extra・行き先
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
        return self::url($query + $extra + ['stay' => self::LEGACY_STAY[$afterSave]]);
    }

    /**
     * 入力エラーの差し戻し先の URL。mode が edit なら id を付ける。
     * mode が空なら close の画面へ戻す。POST の行き先が close でなければ、どの場合にも引き継ぐ
     */
    public static function formUrl(string $kind, string $mode, int $id = 0): string
    {
        $query = ['a' => self::screen($kind, $mode)];
        if ($mode === 'edit') {
            $query['id'] = $id;
        }
        $afterSave = self::postedAfterSave();
        if ($afterSave !== 'close') {
            $query['stay'] = self::LEGACY_STAY[$afterSave];
        }
        return self::url($query);
    }
}
