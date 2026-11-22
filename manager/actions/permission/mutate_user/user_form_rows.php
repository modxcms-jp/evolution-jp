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
 * パスワードの行（変更のチェックと、自動生成・指定の欄と、通知の方法）。
 * 初めは自動生成（g）・画面に表示（s）を選んでおく。
 *
 * @param bool $isNew  新規（見出しは「パスワード」で、チェックは無く欄を初めから出す）
 * @param bool $notify 通知の方法を選ばせる。false は通知の方法を画面に出さず、画面で見せる（s）を送る
 */
function userFormPasswordRow(bool $isNew, bool $notify): string
{
    $genMethod = 'g';
    $notifyMethod = 's';
    ob_start();
    if (!$isNew) {
        ?>
        <input name="newpasswordcheck" type="checkbox"
            onclick="changestate(document.userform.newpassword);changePasswordState(document.userform.newpassword);"><br/>
        <?php
    }
    ?>
    <input name="newpassword" value="<?= $isNew ? 1 : 0 ?>" type="hidden" />
    <?php // 枠は欄の幅 300px で、枠の中の余白は付けない。指定の欄（div）は、ラジオの文字の下に 20px 字下げする（欄ごとの値） ?>
    <div id="passwordBlock" style="display:<?= $isNew ? 'block' : 'none' ?>">
        <fieldset style="width:300px;padding:0;">
            <label>
                <input name="passwordgenmethod" value="g" type="radio"
                    <?= $genMethod === 'spec' ? '' : 'checked="checked"' ?> />
                <?= lang('password_gen_gen') ?>
            </label><br/>
            <label>
                <input name="passwordgenmethod" value="spec" type="radio"
                    <?= $genMethod === 'spec' ? 'checked="checked"' : '' ?>>
                <?= lang('password_gen_specify') ?>
            </label><br/>
            <div style="padding-left:20px">
                <label for="specifiedpassword"><?= lang('change_password_new') ?>:</label>
                <input id="specifiedpassword" name="specifiedpassword" type="password"
                    onkeypress="document.userform.passwordgenmethod[1].checked=true;"
                    size="20" autocomplete="new-password" /><br/>
                <label for="confirmpassword"><?= lang('change_password_confirm') ?>:</label>
                <input id="confirmpassword" name="confirmpassword" type="password"
                    onkeypress="document.userform.passwordgenmethod[1].checked=true;"
                    size="20" autocomplete="new-password" /><br/>
                <div class="form-text"><?= lang('password_rule') ?></div>
            </div>
        </fieldset>
        <?php if ($notify) { ?>
            <fieldset style="width:300px;padding:0;">
                <label>
                    <input name="passwordnotifymethod" value="e" type="radio"
                        <?= $notifyMethod === 'e' ? 'checked="checked"' : '' ?> /><?= lang('password_method_email') ?>
                </label><br/>
                <label>
                    <input name="passwordnotifymethod" value="s" type="radio"
                        <?= $notifyMethod === 'e' ? '' : 'checked="checked"' ?> /><?= lang('password_method_screen') ?>
                </label>
            </fieldset>
        <?php } else { ?>
            <input type="hidden" name="passwordnotifymethod" value="s" />
        <?php } ?>
    </div>
    <?php
    return EvoFieldRow::render([
        'label' => $isNew ? lang('password') : lang('change_password_new'),
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
 * 3 画面が同じ処理を使う JS（パスワードの欄・名前の変更・ブロック・失敗回数のリセット・写真の選択）
 */
function userFormScript(): string
{
    ob_start();
    ?>
<script type="text/javascript">
    function changestate(element) {
        documentDirty = true;
        currval = eval(element).value;
        if (currval == 1) {
            eval(element).value = 0;
        } else {
            eval(element).value = 1;
        }
    }

    function changePasswordState(element) {
        currval = eval(element).value;
        var passwordBlock = document.getElementById('passwordBlock');
        if (passwordBlock) {
            passwordBlock.style.display = currval == 1 ? '' : 'none';
        }
    }

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
