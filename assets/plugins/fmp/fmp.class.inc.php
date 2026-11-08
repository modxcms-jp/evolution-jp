<?php

class ForgotManagerPassword
{
    public $tpl_path;
    private $errors;
    private $lang;

    function __construct()
    {
        $this->tpl_path = str_replace('\\', '/', __DIR__) . '/template/';
        $this->errors = [];
        $this->setLang();
    }

    public function run()
    {
        $i = event()->name;
        if ($i === 'OnManagerLoginFormRender') {
            event()->output($this->OnManagerLoginFormRender());
            return;
        }
        if ($i === 'OnManagerLogin') {
            $this->OnManagerLogin();
            return;
        }
        if ($i === 'OnManagerMainFrameHeaderHTMLBlock') {
            $this->OnManagerMainFrameHeaderHTMLBlock();
            return;
        }
        $fmpkey = get_strvar('fmpkey');
        if ($fmpkey) {
            if ($i === 'OnManagerLoginFormPrerender') {
                $this->OnManagerLoginFormPrerender($fmpkey);
                return;
            }
            if ($i === 'OnManagerAuthentication') {
                event()->output($this->OnManagerAuthentication($fmpkey));
                return;
            }
        }
    }

    private function OnManagerLoginFormRender()
    {
        if (sessionv('fmp_sent')) {
            $fmp_sent = sessionv('fmp_sent');
            unset($_SESSION['fmp_sent']);
            return $fmp_sent;
        }

        if (getv('action') === 'show_form') {
            return $this->getForm();
        }

        if (getv('action') === 'send_email') {
            sessionv('*fmp_sent', $this->sendFmpMail(get_strvar('email'))
                ? $this->lang('email_sent')
                : $this->getErrorOutput() . $this->formLink());
            $this->removeLoginTouchFile();
            evo()->sendRedirect(MODX_MANAGER_URL);
            exit;
        }

        return $this->formLink();
    }

    private function OnManagerAuthentication($fmpkey)
    {
        $this->sweepExpiredTransient();
        $user_id = $this->getUserIdByHash($fmpkey);
        // キーの持ち主以外のユーザー名でログインさせない
        if ($user_id && (int)$user_id !== (int)event()->param('userid')) {
            $user_id = false;
        }
        // 管理者がブロックしたユーザーは、リンクを開いても受け付けない（リンクが無効なときと同じ応答）
        if ($user_id && isUserBlockedByAdmin($this->getUserAttributes($user_id))) {
            $user_id = false;
        }
        if ($user_id) {
            sessionv('*goto_pwd_edit', '1');
            $captchaCode = get_strvar('captcha_code');
            if ($captchaCode) {
                sessionv('*veriword', $captchaCode);
            }
            $this->unBlock($user_id);
        }
        return $user_id ? true : false;
    }

    private function OnManagerLogin()
    {
        $this->sweepTransient(
            evo()->getLoginUserID()
        );
    }

    private function OnManagerMainFrameHeaderHTMLBlock()
    {
        if (!sessionv('goto_pwd_edit')) {
            return;
        }
        unset($_SESSION['goto_pwd_edit']);
        evo()->sendRedirect(MODX_MANAGER_URL . '?a=28');
    }

    private function OnManagerLoginFormPrerender($fmpkey)
    {
        // ログイン画面を描画せずに exit するため、ここで消さないと次の表示がセーフモードになる
        $this->removeLoginTouchFile();
        // 期限切れのキーもここで無効にして、使用済みと同じ案内を出す
        $this->sweepExpiredTransient();
        $user = $this->getUserByHash($fmpkey);
        if (!isset($user['email'])) {
            header('Content-Type: text/html; charset=UTF-8');
            $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
            exit(
                sprintf(
                    '<script>alert(%s);location.href=%s;</script>',
                    json_encode($this->lang('fmp_link_invalid'), $flags),
                    json_encode(MODX_MANAGER_URL, $flags)
                )
            );
        }
        header(
            sprintf(
                'Location:%s/processors/login.processor.php?username=%s&fmpkey=%s%s',
                rtrim(MODX_MANAGER_URL, '/'),
                urlencode($user['email']),
                urlencode($fmpkey),
                evo()->config('use_captcha') ? '&captcha_code=ignore' : ''
            )
        );
        exit;
    }

    private function sendFmpMail($email)
    {
        // 未入力のアドレスはメールアドレスが空のユーザーに一致しうるので、登録の無いアドレスと同じに扱う
        $user_id = ($email === null || $email === '') ? 0 : $this->getUserIdByEmail($email);
        if (!$user_id) {
            // 登録の無いアドレスでも、送信したときと同じ応答にする（メールは送らない）
            return true;
        }
        if (isUserBlockedByAdmin($this->getUserAttributes($user_id))) {
            // 管理者がブロックしたユーザーにも、送信したときと同じ応答にする（メールは送らない）
            return true;
        }
        $this->sweepTransient($user_id);
        // リンクだけでログインできるので、推測できない乱数で作る（96 ビット。URL 向けの base64 で 16 文字）
        $fmpkey = strtr(base64_encode(random_bytes(12)), '+/', '-_');
        $this->addTransient($user_id, 'fmp_hash', $fmpkey);
        $this->addTransient($user_id, 'fmp_expire', date('Y-m-d H:i:00', strtotime('+30 minutes')));

        if (!$this->send($email, $fmpkey)) {
            $this->errors[] = $this->lang('error_sending_email');
            return false;
        }
        return true;
    }

    function send($email, $fmpkey)
    {
        return evo()->sendmail(
            [
                'subject' => $this->lang('password_change_request'),
                'sendto' => $email,
                'type' => 'text'
            ],
            evo()->parseDocumentSource(
                evo()->parseText(
                    file_get_contents($this->tpl_path . 'sendmail.tpl'),
                    [
                        'intro' => $this->lang('forgot_password_email_intro'),
                        'fmpkey' => $fmpkey . (evo()->config('use_captcha') ? '&captcha_code=ignore' : ''),
                        'link' => $this->lang('forgot_password_email_link'),
                        'instructions' => $this->lang('forgot_password_email_instructions'),
                        'fine_print' => $this->lang('forgot_password_email_fine_print')
                    ]
                )
            )
        );
    }

    private function lang($key, $default = null)
    {
        if (!isset($this->lang[$key])) {
            return $default;
        }
        return $this->lang[$key];
    }

    private function setLang()
    {
        static $en = null;
        if (!$en) {
            $en = include __DIR__ . '/lang/en.php';
        }
        foreach ($en as $key => $default) {
            $this->lang[$key] = lang($key, $default);
        }
    }

    private function formLink()
    {
        return sprintf(
            '<a href="?action=show_form" id="ForgotManagerPassword-show_form">%s</a>',
            $this->lang('forgot_your_password')
        );
    }

    private function getErrorOutput()
    {
        if (!$this->errors) {
            return '';
        }
        return implode(
            "\n",
            array_map(
                function ($v) {
                    return sprintf('<span class="error">%s</span>', $v);
                },
                $this->errors
            )
        );
    }

    private function removeLoginTouchFile()
    {
        $touch_path = MODX_CACHE_PATH . 'touch.siteCache.idx.php';
        if (is_file($touch_path)) {
            @unlink($touch_path);
        }
    }

    // 外すのは失敗回数による一時ロックだけ。管理者が付けたブロック（blocked など）には触らない
    private function unBlock($user_id)
    {
        $fields = ['failedlogincount' => 0];
        if (isTemporaryLoginLock($this->getUserAttributes($user_id))) {
            $fields['blockeduntil'] = 0;
        }
        db()->update(
            $fields,
            '[+prefix+]user_attributes',
            sprintf("internalKey='%d'", $user_id)
        );
    }

    private function getUserAttributes($user_id)
    {
        return db()->getRow(
            db()->select(
                'blocked, blockeduntil, blockedafter, failedlogincount',
                '[+prefix+]user_attributes',
                sprintf("internalKey='%d'", $user_id)
            )
        );
    }

    private function getForm()
    {
        return evo()->parseText(
            file_get_contents($this->tpl_path . 'form.tpl'),
            $this->lang
        );
    }

    private function addTransient($user_id, $key, $value)
    {
        return db()->insert(
            db()->escape(
                [
                    'user' => $user_id,
                    'setting_name' => $key,
                    'setting_value' => $value
                ]
            ),
            '[+prefix+]user_settings'
        );
    }

    private function sweepTransient($user_id)
    {
        db()->delete(
            '[+prefix+]user_settings',
            [
                sprintf("user=%s", $user_id),
                "AND setting_name LIKE 'fmp_%'"
            ]
        );
    }

    private function sweepExpiredTransient()
    {
        // fmp_expire は PHP の date() で保存しているので、判定も PHP 側で行う
        // （MySQL の unix_timestamp() は DB のタイムゾーンで解釈するため、PHP とずれると即座に期限切れになる）
        $rs = db()->select(
            'user, setting_value',
            '[+prefix+]user_settings',
            "setting_name='fmp_expire'"
        );
        $user_ids = [];
        $now = time();
        while ($row = db()->getRow($rs)) {
            $expire = strtotime($row['setting_value']);
            if ($expire === false || $expire < $now) {
                $user_ids[] = $row['user'];
            }
        }
        if (!$user_ids) {
            return;
        }
        db()->delete(
            '[+prefix+]user_settings',
            [
                where_in('user', $user_ids),
                "AND setting_name LIKE 'fmp_%'"
            ]
        );
    }

    private function getUserByHash($fmpkey)
    {
        if (!$fmpkey) {
            return false;
        }
        $user_id = $this->getUserIdByHash($fmpkey);
        if (!$user_id) {
            return false;
        }

        return db()->getRow(
            db()->select(
                '*',
                '[+prefix+]user_attributes',
                sprintf("internalKey='%s'", $user_id),
                null,
                1
            )
        );
    }

    private function getUserIdByEmail($email)
    {
        return db()->getValue(
            db()->select(
                'internalKey',
                '[+prefix+]user_attributes',
                sprintf("email='%s'", db()->escape($email))
            )
        );
    }

    private function getUserIdByHash($fmpkey)
    {
        if (empty($fmpkey)) {
            return false;
        }
        return db()->getValue(
            db()->select(
                'user',
                '[+prefix+]user_settings',
                sprintf("setting_name='fmp_hash' AND setting_value='%s'", db()->escape($fmpkey))
            )
        );
    }
}
