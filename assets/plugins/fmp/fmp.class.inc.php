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
        if (getv('fmpkey')) {
            if ($i === 'OnManagerLoginFormPrerender') {
                $this->OnManagerLoginFormPrerender(getv('fmpkey'));
                return;
            }
            if ($i === 'OnManagerAuthentication') {
                event()->output($this->OnManagerAuthentication(getv('fmpkey')));
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
            $_SESSION['fmp_sent'] = $this->sendFmpMail(getv('email'))
                ? $this->lang('email_sent')
                : $this->getErrorOutput() . $this->formLink();
            if (is_file(MODX_CACHE_PATH . 'touch.siteCache.idx.php')) {
                unlink(MODX_CACHE_PATH . 'touch.siteCache.idx.php');
            }
            evo()->sendRedirect(MODX_MANAGER_URL);
            exit;
        }

        return $this->formLink();
    }

    private function OnManagerAuthentication($fmpkey)
    {
        $this->sweepExpiredTransient();
        $user_id = $this->getUserIdByHash($fmpkey);
        if ($user_id) {
            $_SESSION['goto_pwd_edit'] = '1';
            if (getv('captcha_code')) {
                $_SESSION['veriword'] = getv('captcha_code');
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
        $user = $this->getUserByHash($fmpkey);
        if (!isset($user['email'])) {
            exit($this->lang('user_doesnt_exist'));
        }
        header(
            sprintf(
                'Location:%s/processors/login.processor.php?username=%s&fmpkey=%s%s',
                rtrim(MODX_MANAGER_URL, '/'),
                $user['email'],
                $fmpkey,
                evo()->config('use_captcha') ? '&captcha_code=ignore' : ''
            )
        );
        exit;
    }

    private function sendFmpMail($email)
    {
        $user_id = $this->getUserIdByEmail($email);
        if (!$user_id) {
            exit($this->lang('could_not_find_user'));
        }
        $this->sweepTransient($user_id);
        $fmpkey = easy_hash($email . mt_rand());
        $this->addTransient($user_id, 'fmp_hash', $fmpkey);
        $this->addTransient($user_id, 'fmp_expire', date('Y-m-d H:i:00', strtotime('+30 minutes')));

        if (!$this->send($email, $fmpkey)) {
            $this->errors[] = $this->lang('error_sending_email');
            return false;
        }
        $this->errors[] = $this->lang('error_sending_email');
        return true;
    }

    function send($email, $fmpkey)
    {
        return evo()->sendmail(
            [
                'subject' => $this->lang('password_change_request'),
                'sendto' => $email
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

    private function unBlock($user_id)
    {
        db()->update(
            [
                'blocked' => 0,
                'blockeduntil' => 0,
                'failedlogincount' => 0
            ],
            '[+prefix+]user_attributes',
            sprintf("internalKey='%s'", $user_id)
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
        $rs = db()->select(
            'user',
            '[+prefix+]user_settings',
            sprintf(
                "setting_name='fmp_expire' AND unix_timestamp(setting_value)<%s", time()
            )
        );
        $user_ids = [];
        while ($row = db()->getRow($rs)) {
            $user_ids[] = $row['user'];
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
                sprintf("setting_name='fmp_hash' AND setting_value='%s'", $fmpkey)
            )
        );
    }
}
