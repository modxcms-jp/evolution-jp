<?php
/**
 * 管理ユーザー・Web ユーザー・プロフィールの 3 つの編集画面が共有する行と JS。
 * 画面ごとの違いは、引数の値と少しのオプションで表す（この中に画面ごとの分岐を足さない）。
 * 値のエスケープは行ごとに hsc() で行い、行は EvoFieldRow::render() で出す
 */

/**
 * プロフィールの 13 行（氏名・電話・携帯・FAX・住所・市区町村・都道府県・郵便番号・国・生年月日・性別・コメント・写真）
 *
 * @param array $data      値（fullname・phone・mobilephone・fax・street・city・state・zip・country・dob・gender・comment・photo）
 * @param array $countries 国の一覧 [コード => 名前]
 */
function userFormProfileRows(array $data, array $countries): string
{
    // 文字の欄。name => [見出しの言語キー, autocomplete, 変えたとき documentDirty にするか]
    $textRows = [
        'fullname' => ['user_full_name', 'name', false],
        'phone' => ['user_phone', 'tel', false],
        'mobilephone' => ['user_mobile', 'tel', false],
        'fax' => ['user_fax', 'tel', false],
        'street' => ['user_street', 'street-address', true],
        'city' => ['user_city', 'address-level2', true],
        'state' => ['user_state', 'address-level1', false],
        'zip' => ['user_zip', 'postal-code', false],
    ];
    $html = '';
    foreach ($textRows as $name => [$labelKey, $autocomplete, $dirty]) {
        $html .= EvoFieldRow::render([
            'label' => lang($labelKey),
            'body' => sprintf(
                '<input type="text" name="%s" value="%s" class="inputBox" autocomplete="%s"%s />',
                $name,
                hsc($data[$name] ?? ''),
                $autocomplete,
                $dirty ? ' onchange="documentDirty=true;"' : ''
            ),
        ]);
    }

    // 国
    $chosenCountry = $data['country'] ?? '';
    $options = '<option value=""' . ($chosenCountry ? '' : ' selected="selected"') . ">&nbsp;</option>\n";
    foreach ($countries as $key => $country) {
        $options .= sprintf(
            '<option value="%s"%s>%s</option>' . "\n",
            $key,
            $chosenCountry == $key ? ' selected="selected"' : '',
            $country
        );
    }
    $html .= EvoFieldRow::render([
        'label' => lang('user_country'),
        'body' => '<select size="1" name="country" class="inputBox" autocomplete="country-name">' . $options . '</select>',
    ]);

    // 生年月日と、日付を消すボタン
    ob_start();
    ?>
    <input type="text" id="dob" name="dob" class="DatePicker"
        value="<?= manager()->dateInputValue($data['dob'] ?? null, 'dateOnly') ?>" onblur="documentDirty=true;">
    <a class="clickable-label" onclick="document.userform.dob.value=''; return true;"><img
            src="<?= manager_style_image_path('icons') ?>cal_nodate.gif"
            alt="<?= lang('remove_date') ?>"></a>
    <?php
    $html .= EvoFieldRow::render([
        'label' => lang('user_dob'),
        'body' => ob_get_clean(),
    ]);

    // 性別
    $gender = $data['gender'] ?? '';
    $options = '<option value="0"></option>';
    foreach ([1 => 'user_male', 2 => 'user_female', 3 => 'user_other'] as $value => $labelKey) {
        $options .= sprintf(
            '<option value="%d"%s>%s</option>',
            $value,
            $gender == $value ? ' selected="selected"' : '',
            lang($labelKey)
        );
    }
    $html .= EvoFieldRow::render([
        'label' => lang('user_gender'),
        'body' => '<select name="gender" class="inputBox">' . $options . '</select>',
    ]);

    $html .= EvoFieldRow::render([
        'label' => lang('comment'),
        'body' => '<textarea name="comment" class="inputBox" rows="5">' . hsc($data['comment'] ?? '') . '</textarea>',
    ]);

    // 写真。選んだ写真は SetUrl() がプレビュー（img[name=iphoto]）に出す。
    // 説明（div.form-text）を値のセルに直に書くのは、説明のあとにプレビューの画像を置く並びを保つため（help は値の最後に付く）
    if (!empty($data['photo'])) {
        $photo = $data['photo'];
    } else {
        $photo = config('base_url') . 'manager/' . style('tx');
    }
    if (substr($photo, 0, 1) !== '/' && !preg_match('@^https?://@', $photo)) {
        $photo = config('base_url') . $photo;
    }
    ob_start();
    ?>
    <input type="text" maxlength="255" style="width: 150px;" name="photo"
        value="<?= hsc($data['photo'] ?? '') ?>" />
    <input type="button" value="<?= lang('insert') ?>" onclick="BrowseServer();" />
    <div class="form-text"><?= lang('user_photo_message') ?></div>
    <div><img name="iphoto" src="<?= hsc($photo) ?>" /></div>
    <?php
    $html .= EvoFieldRow::render([
        'label' => lang('user_photo'),
        'body' => ob_get_clean(),
    ]);

    return $html;
}

/**
 * パスワードの行（管理ユーザー・Web ユーザー・プロフィール・パスワードの変更で共通）。
 * 欄（右端に目のアイコン）とコピー、決まりの一言、文字数の案内、自動生成のオプションの枠（文字数・文字種・自動生成のボタン）、保存後の注意の順に並べる。
 * 動きは manager/media/script/password-field.js、決まりは EvoPasswordPolicy（assets/docs/password-policy.md）。
 * 送る値: newpassword（変えるとき 1）と userpassword（新しいパスワード。閉じているときは送らない）
 *
 * @param string $state new（新規。欄を出し、初めに自動生成する）/ set（すでにある。「設定済み」と変更のボタン）/
 *                      readonly（すでにあり、変える権限が無い。「設定済み」だけ）/ change（パスワードの変更の画面。欄を出し、自動生成はしない）
 * @param array  $o     restored（入力エラーで戻した画面で、パスワードを変えようとしていた。欄を開き、消したことを知らせる）/
 *                      self（自分のパスワード。本人へ伝える注意を出さない）/
 *                      username（パスワード管理ツールに、どの利用者のパスワードかを知らせる名前。画面にログイン名の欄が無いとき）
 */
function userFormPasswordRow(string $state, array $o = []): string
{
    $restored = !empty($o['restored']);
    $username = (string) ($o['username'] ?? '');
    $open = $state === 'new' || $state === 'change' || ($state === 'set' && $restored);
    $labels = [
        'new' => lang('password_initial'),
        'change' => lang('change_password_new'),
    ];
    $messages = [
        'copied' => lang('password_copied'),
        'copyFailed' => lang('password_copy_failed'),
        'generated' => lang('password_generated'),
        'generateUnavailable' => lang('password_generate_unavailable'),
        'restored' => lang('password_restored'),
        'required' => lang('password_required'),
        'tooShort' => lang('password_too_short_notice'),
        'belowRecommended' => lang('password_below_recommended'),
        'tooLong' => lang('password_too_long_notice'),
        'lengthValue' => lang('password_length_value'),
    ];
    $button = static function (string $action, string $html, string $title, string $class = 'button', string $extra = ''): string {
        return sprintf(
            '<button type="button" class="%s" data-password-action="%s" title="%s" aria-label="%s"%s>%s</button>',
            $class,
            $action,
            hsc($title),
            hsc($title),
            $extra,
            $html
        );
    };
    $icon = static function (string $src): string {
        return sprintf('<img src="%s" alt="" aria-hidden="true" />', hsc($src));
    };
    $lengthText = static function (int $count): string {
        return hsc(str_replace('%count%', (string) $count, lang('password_length_value')));
    };
    $notice = lang('password_not_recoverable') . (empty($o['self']) ? ' ' . lang('password_tell_safely') : '');
    ob_start();
    ?>
    <div class="password-setting" id="passwordSetting"
        data-password-open="<?= $open ? 1 : 0 ?>"
        data-password-generate="<?= $state === 'new' ? 1 : 0 ?>"
        data-password-restored="<?= $restored && $open ? 1 : 0 ?>">
        <input type="hidden" name="newpassword" value="<?= $open ? 1 : 0 ?>" data-password-flag />
        <?php if ($username !== '') { ?>
            <input type="text" hidden readonly autocomplete="username" value="<?= hsc($username) ?>" />
        <?php } ?>
        <?php if ($state === 'set' || $state === 'readonly') { ?>
            <div class="password-setting-current" data-password-current<?= $open ? ' hidden' : '' ?>>
                <span><?= lang('password_is_set') ?></span>
                <?php if ($state === 'set') {
                    echo $button('open', hsc(lang('password_change_button')), lang('password_change_button'));
                } ?>
            </div>
        <?php } ?>
        <?php if ($state !== 'readonly') { ?>
            <div class="password-setting-panel" data-password-panel<?= $open ? '' : ' hidden' ?>>
                <div class="password-setting-field">
                    <?php // 目のアイコンはログイン画面と同じもの・同じ状態の表し方（aria-pressed。押すと表示） ?>
                    <div class="password-setting-input">
                        <input type="password" name="userpassword" id="userpassword" class="inputBox" value=""
                            placeholder="<?= hsc(lang($state === 'new' ? 'password_placeholder_new' : 'password_placeholder_change')) ?>"
                            autocomplete="new-password" spellcheck="false" autocapitalize="off" autocorrect="off"
                            aria-describedby="passwordSettingRule passwordSettingNote passwordSettingStatus"
                            data-password-input<?= $open ? '' : ' disabled' ?> />
                        <?= $button(
                            'toggle',
                            file_get_contents(MODX_MANAGER_PATH . 'media/style/common/password_toggle_icons.tpl'),
                            lang('show_password'),
                            'toggle-password',
                            ' aria-controls="userpassword" aria-pressed="false"'
                        ) ?>
                    </div>
                    <?= $button('copy', $icon(style('icons_resource_duplicate')) . ' ' . hsc(lang('password_copy')), lang('password_copy_title'), 'button', ' disabled') ?>
                </div>
                <div class="form-text" id="passwordSettingRule">
                    <?= hsc(sprintf(lang('password_rule'), EvoPasswordPolicy::MIN_LENGTH, EvoPasswordPolicy::RECOMMENDED_LENGTH)) ?>
                    <img src="<?= hsc(style('icons_tooltip_over')) ?>" class="tooltip" tabindex="0"
                        title="<?= hsc(sprintf(lang('password_rule_detail'), EvoPasswordPolicy::MAX_BYTES)) ?>"
                        alt="<?= hsc(sprintf(lang('password_rule_detail'), EvoPasswordPolicy::MAX_BYTES)) ?>" />
                </div>
                <div class="password-setting-note" id="passwordSettingNote" data-password-note aria-live="polite" hidden></div>
                <div class="password-setting-note is-warning" data-password-fullwidth hidden><?= lang('password_fullwidth_notice') ?></div>
                <div class="password-setting-status" id="passwordSettingStatus" data-password-status role="status" aria-live="polite"></div>
                <div class="password-setting-options" role="group" aria-labelledby="passwordSettingOptions">
                    <div class="password-setting-options-head">
                        <span id="passwordSettingOptions"><?= lang('password_generate_options') ?></span>
                        <output data-password-length-value for="passwordLength"><?= $lengthText(EvoPasswordPolicy::GENERATE_LENGTH) ?></output>
                    </div>
                    <div class="password-setting-length">
                        <?= $button('shorter', '−', lang('password_length_shorter')) ?>
                        <input type="range" id="passwordLength" data-password-length
                            min="<?= EvoPasswordPolicy::GENERATE_MIN ?>" max="<?= EvoPasswordPolicy::GENERATE_MAX ?>"
                            step="1" value="<?= EvoPasswordPolicy::GENERATE_LENGTH ?>"
                            aria-label="<?= hsc(lang('password_length_label')) ?>" />
                        <?= $button('longer', '+', lang('password_length_longer')) ?>
                    </div>
                    <div class="password-setting-scale" aria-hidden="true">
                        <span><?= $lengthText(EvoPasswordPolicy::GENERATE_MIN) ?></span>
                        <span><?= $lengthText(EvoPasswordPolicy::GENERATE_MAX) ?></span>
                    </div>
                    <div class="password-setting-classes">
                        <label><input type="checkbox" data-password-class="symbols" /><?= lang('password_class_symbols') ?></label>
                        <label><input type="checkbox" data-password-class="upper" checked /><?= lang('password_class_upper') ?></label>
                        <label><input type="checkbox" data-password-class="digits" checked /><?= lang('password_class_digits') ?></label>
                    </div>
                    <div class="password-setting-generate">
                        <?= $button('generate', $icon(style('icons_refresh')) . ' ' . hsc(lang('password_generate')), lang('password_generate_title'), 'button primary') ?>
                    </div>
                </div>
                <div class="form-text"><?= $notice ?></div>
                <?php if ($state === 'set') { ?>
                    <div class="password-setting-actions">
                        <?= $button('cancel', hsc(lang('password_change_cancel')), lang('password_change_cancel')) ?>
                    </div>
                <?php } ?>
            </div>
            <script src="<?= manager_asset('media/script/password-field.js') ?>"></script>
            <script>
                EvoPasswordField.init(
                    document.getElementById('passwordSetting'),
                    <?= js_value(EvoPasswordPolicy::clientConfig()) ?>,
                    <?= js_value($messages) ?>
                );
            </script>
        <?php } ?>
    </div>
    <?php
    return EvoFieldRow::render([
        'field' => 'password',
        'label' => $labels[$state] ?? lang('password'),
        'body' => ob_get_clean(),
    ]);
}

/**
 * メールの行（oldemail は変更前の値）。
 * 入力エラーで戻したときは送られた oldemail をそのまま使う（新規なら空のまま）。開いたときは今のメール
 */
function userFormEmailRow(array $data): string
{
    return EvoFieldRow::render([
        'label' => lang('user_email'),
        'body' => sprintf(
            '<input type="text" name="email" value="%s" class="inputBox" autocomplete="email" />'
            . '<input type="hidden" name="oldemail" value="%s" />',
            hsc($data['email'] ?? ''),
            hsc($data['oldemail'] ?? ($data['email'] ?? ''))
        ),
    ]);
}

/**
 * ユーザー名の行と、名前を変えるリンクの行
 *
 * @param array $o username（入力欄の値）・oldusername（変更前の名前）・hasName（すでにあるユーザー。名前の行と変えるリンクを出す）・
 *                 editing（初めから入力欄を出す）・confirmRename（変えるときに確かめる）
 */
function userFormNameRows(array $o): string
{
    $html = '';
    if (!empty($o['hasName'])) {
        $html .= EvoFieldRow::render([
            'label' => '',
            'body' => sprintf(
                '<img src="%s"/>&nbsp;<b>%s</b> - <span class="comment"><a href="#" onclick="changeName(%s);return false;">%s</a></span>'
                . '<input type="hidden" name="oldusername" value="%s" />',
                style('icons_user'),
                hsc($o['oldusername']),
                !empty($o['confirmRename']) ? 'true' : 'false',
                lang('change_name'),
                hsc($o['oldusername'])
            ),
            'attr' => ['id' => 'showname'] + (!empty($o['editing']) ? ['style' => 'display:none'] : []),
        ]);
    }
    $html .= EvoFieldRow::render([
        'label' => lang('username'),
        'body' => sprintf(
            '<input type="text" name="newusername" value="%s" class="inputBox" maxlength="100" autocomplete="username" />',
            hsc($o['username'])
        ),
        'attr' => ['id' => 'editname'] + (!empty($o['editing']) ? [] : ['style' => 'display:none']),
    ]);
    return $html;
}

/**
 * ブロック中の知らせの行（#blocked は changeblockstate() が内容を差し替える）。
 * すでにあるユーザーは常に出し、新規はブロック中のときだけ出す
 */
function userFormBlockedNotice(array $data, bool $exists): string
{
    $blocked = isUserBlocked($data);
    if (!$exists && !$blocked) {
        return '';
    }
    return EvoFieldRow::render([
        'label' => '',
        'body' => '<span id="blocked" class="warning">'
            . ($blocked ? '<b>' . lang('user_is_blocked') . '</b>' : '')
            . '</span>',
    ]);
}

/**
 * ログインの状態の行（設定ではない。ログイン回数・前回のログイン・失敗回数・ブロックと、その日時）
 */
function userFormLoginStateRows(array $data): string
{
    $html = EvoFieldRow::render([
        'label' => lang('user_logincount'),
        'body' => (int)($data['logincount'] ?? 0),
    ]);

    $html .= EvoFieldRow::render([
        'label' => lang('user_prevlogin'),
        'body' => !empty($data['lastlogin'])
            ? evo()->toDateFormat($data['lastlogin'] + config('server_offset_time'))
            : '-',
    ]);

    ob_start();
    ?>
    <input type="hidden" name="failedlogincount" value="<?= (int)($data['failedlogincount'] ?? 0) ?>">
    <span id="failed"><?= (int)($data['failedlogincount'] ?? 0) ?></span>&nbsp;&nbsp;&nbsp;[<a
        href="javascript:resetFailed()"><?= lang('reset_failedlogins') ?></a>]
    <?php
    $html .= EvoFieldRow::render([
        'label' => lang('user_failedlogincount'),
        'body' => ob_get_clean(),
    ]);

    ob_start();
    ?>
    <label>
        <input
            name="blockedcheck"
            type="checkbox"
            onclick="changeblockstate(document.userform.blockedmode, document.userform.blockedcheck);"
            <?= isUserBlocked($data) ? 'checked' : '' ?>>
        <input type="hidden" name="blocked" value="<?= !empty($data['blocked']) ? 1 : 0 ?>">
    </label>
    <?php
    $html .= EvoFieldRow::render([
        'label' => lang('user_block'),
        'body' => ob_get_clean(),
    ]);

    // ブロックの日時と、日時を消すボタン
    foreach (['blockedafter' => 'user_blockedafter', 'blockeduntil' => 'user_blockeduntil'] as $name => $labelKey) {
        ob_start();
        ?>
        <input type="text" id="<?= $name ?>" name="<?= $name ?>" class="DatePicker"
            value="<?= manager()->dateInputValue($data[$name] ?? null) ?>"
            onblur="documentDirty=true;" readonly="readonly">
        <a class="clickable-label" onclick="document.userform.<?= $name ?>.value=''; return true;"><img
                src="<?= manager_style_image_path('icons') ?>cal_nodate.gif"
                alt="<?= lang('remove_date') ?>" /></a>
        <?php
        $html .= EvoFieldRow::render([
            'label' => lang($labelKey),
            'body' => ob_get_clean(),
        ]);
    }

    return $html;
}

/**
 * 3 画面が同じ処理を使う JS（名前の変更・ブロック・失敗回数のリセット・写真の選択。パスワードの欄は password-field.js）
 */
function userFormScript(): string
{
    ob_start();
    ?>
<script type="text/javascript">
    function changeblockstate(element, checkelement) {
        var blocked = document.getElementById('blocked');
        currval = eval(element).value;
        if (currval == 1) {
            if (confirm(<?= js_literal(stripcslashes(lang('confirm_unblock'))) ?>) == true) {
                document.userform.blocked.value = 0;
                document.userform.blockeduntil.value = "";
                document.userform.blockedafter.value = "";
                document.userform.failedlogincount.value = 0;
                if (blocked) {
                    blocked.replaceChildren(Object.assign(document.createElement("b"), {textContent: <?= js_literal(stripcslashes(lang('unblock_message'))) ?>}));
                    blocked.className = "TD";
                }
                eval(element).value = 0;
            } else {
                eval(checkelement).checked = true;
            }
        } else {
            if (confirm(<?= js_literal(stripcslashes(lang('confirm_block'))) ?>) == true) {
                document.userform.blocked.value = 1;
                if (blocked) {
                    blocked.replaceChildren(Object.assign(document.createElement("b"), {textContent: <?= js_literal(stripcslashes(lang('block_message'))) ?>}));
                    blocked.className = "warning";
                }
                eval(element).value = 1;
            } else {
                eval(checkelement).checked = false;
            }
        }
    }

    function resetFailed() {
        document.userform.failedlogincount.value = 0;
        document.getElementById("failed").innerHTML = "0";
    }

    // 名前の入力欄を出す。confirmFirst が true のときは、確かめてから出す
    function changeName(confirmFirst) {
        if (confirmFirst && !confirm(<?= js_literal(stripcslashes(lang('confirm_name_change'))) ?>)) {
            return;
        }
        document.getElementById("showname").style.display = "none";
        document.getElementById("editname").style.display = "";
    }

    function BrowseServer() {
        window.evoOpenFilePicker(<?= js_literal(config('base_url') . 'manager/media/browser/evo/browser.php?type=images') ?>);
    }

    function SetUrl(url, width, height, alt) {
        document.userform.photo.value = url;
        document.images['iphoto'].src = <?= js_literal(config('base_url')) ?> + url;
    }
</script>
    <?php
    return ob_get_clean();
}
