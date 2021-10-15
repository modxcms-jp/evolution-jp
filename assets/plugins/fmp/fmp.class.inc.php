<?php
class ForgotManagerPassword {
    public $tpl_path;
    private $errors;
    private $lang;

    function __construct() {
        $this->tpl_path = str_replace('\\', '/', __DIR__) . '/template/';
        $this->errors = array();
        $this->setLang();
    }

    public function run() {
        $i = event()->name;

        if (getv('fmpkey') && $i === 'OnManagerLoginFormPrerender') {
            $this->redirectLoginProcessor(getv('fmpkey'));
            return;
        }

        if ($i === 'OnManagerLoginFormRender') {
            event()->output(
                $this->showPrompt(
                    getv('action'),
                    getv('email')
                )
            );
            return;
        }

        if ($i === 'OnManagerAuthentication') {
            $this->sweepExpiredTransient();
            if(!getv('fmpkey')) {
                return;
            }
            $user_id = $this->getUserIdByHash(getv('fmpkey'));
            if($user_id) {
                $_SESSION['mgrForgetPassword'] = '1';
                if (getv('captcha_code')) {
                    $_SESSION['veriword'] = getv('captcha_code');
                }
                $this->unBlock($user_id);
            }
            event()->output($user_id ? true : false);
        }

        if($i === 'OnManagerLogin') {
            $this->sweepTransient(
                evo()->getLoginUserID()
            );
        }

        if ($i === 'OnManagerChangePassword') {
            if (isset($_SESSION['mgrForgetPassword'])) {
                unset($_SESSION['mgrForgetPassword']);
            }
            return;
        }
    }

    private function lang($key, $default=null) {
        if(!isset($this->lang[$key])) {
            return $default;
        }
        return $this->lang[$key];
    }

    private function setLang() {
        $en = array(
            'forgot_your_password'               => 'Forgot your password?',
            'account_email'                      => 'Account email',
            'send'                               => 'Send',
            'password_change_request'            => 'Password change request',
            'forgot_password_email_intro'        => 'A request has been made to change the password on your account.',
            'forgot_password_email_link'         => 'Click here to complete the process.',
            'forgot_password_email_instructions' => 'From there you will be able to change your password from the My Account menu.',
            'forgot_password_email_fine_print'   => '* The URL above will expire once you change your password or after today.',
            'error_sending_email'                => 'Error sending email',
            'could_not_find_user'                => 'Could not find user',
            'user_doesnt_exist'                  => 'User does not exist',
            'email_sent'                         => 'Email sent',
        );
        foreach($en as $key=>$value) {
            $this->lang[$key] = lang($key, $value);
        }
    }

    private function redirectLoginProcessor($fmpkey) {
        $user = $this->getUserByHash($fmpkey);
        if(!isset($user['email'])) {
            exit('ユーザが存在しません。');
        }


        header(
            sprintf(
                'Location:%smanager/processors/login.processor.php?username=%s&fmpkey=%s%s',
                MODX_SITE_URL,
                $user['email'],
                $fmpkey,
                evo()->config('use_captcha') ? '&captcha_code=ignore' : ''
            )
        );
        exit;
    }

    private function showPrompt($action,$email) {
        if ($action==='show_form') {
            return $this->getForm();
        }

        $link = sprintf(
            '<a href="index.php?action=show_form" id="ForgotManagerPassword-show_form">%s</a>'
            , $this->lang('forgot_your_password')
        );

        if($action !== 'send_email') {
            return $link;
        }
        if (!$this->sendEmail($email)) {
            return $this->getErrorOutput() . $link;
        }
        return $this->lang('email_sent');

    }

    private function getErrorOutput() {
        if($this->errors) {
            return sprintf(
                '<span class="error">%s</span>'
                , implode('</span><span class="errors">', $this->errors)
            );
        }
        return '';
    }

    private function unBlock($user_id) {
        db()->update(
            array(
                'blocked'          => 0,
                'blockeduntil'     => 0,
                'failedlogincount' => 0
            ),
            '[+prefix+]user_attributes',
            sprintf("internalKey='%s'", $user_id)
        );
    }

    private function getForm()
    {
        return evo()->parseText(
            file_get_contents($this->tpl_path . 'form.tpl')
            , $this->lang
        );
    }

    private function getUserByHash($fmpkey) {
        if(!$fmpkey) {
            return false;
        }
        $user_id = $this->getUserIdByHash($fmpkey);
        if(!$user_id) {
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

    private function getUserIdByHash($fmpkey) {
        if(empty($fmpkey)) {
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

    private function readTransient($user_id, $key) {
        return db()->getValue(
            db()->select(
                'setting_value',
                '[+prefix+]user_settings',
                sprintf(
                    "user='%s' AND setting_name='%s'",
                    db()->escape($user_id),
                    db()->escape($key)
                )
            )
        );
    }

    private function addTransient($user_id, $key, $value) {
        return db()->insert(
            db()->escape(
                array(
                    'user' => $user_id,
                    'setting_name' => $key,
                    'setting_value' => $value
                )
            ),
            '[+prefix+]user_settings',
        );
    }

    private function sweepTransient($user_id) {
        return db()->delete(
            '[+prefix+]user_settings',
            array(
                sprintf("user=%s", $user_id),
                "AND setting_name LIKE 'fmp_%'"
            )
        );
    }

    private function sweepExpiredTransient() {
        $rs = db()->select(
            'user',
            '[+prefix+]user_settings',
            sprintf(
                "setting_name='fmp_expire' AND unix_timestamp(setting_value)<%s", time()
            )
        );
        $user_ids = array();
        while($row = db()->getRow($rs)) {
            $user_ids[] = $row['user'];
        }
        if(!$user_ids) {
            return;
        }
        db()->delete(
            '[+prefix+]user_settings',
            array(
                where_in('user', $user_ids),
                "AND setting_name LIKE 'fmp_%'"
            )
        );
    }

    /* Send an email with a link to login */
    private function sendEmail($email) {
        $user_id = db()->getValue(
            db()->select(
                'internalKey',
                '[+prefix+]user_attributes',
                sprintf("email='%s'", db()->escape($email))
            )
        );
        if(!$user_id) {
            exit($this->lang('could_not_find_user'));
        }
        $this->sweepTransient($user_id);
        $fmpkey = easy_hash($email . mt_rand());
        $this->addTransient($user_id, 'fmp_hash', $fmpkey);
        $this->addTransient($user_id, 'fmp_expire', date('Y-m-d H:i:00', strtotime('+30 minutes')));

        $result = evo()->sendmail(
            array(
                'subject' => $this->lang('password_change_request'),
                'sendto' => $email
            ),
            evo()->parseDocumentSource(
                evo()->parseText(
                    file_get_contents($this->tpl_path . 'sendmail.tpl')
                    , array(
                        'intro'        => $this->lang('forgot_password_email_intro'),
                        'fmpkey'       => $fmpkey . (evo()->config('use_captcha') ? '&captcha_code=ignore' : ''),
                        'link'         => $this->lang('forgot_password_email_link'),
                        'instructions' => $this->lang('forgot_password_email_instructions'),
                        'fine_print'   => $this->lang('forgot_password_email_fine_print')
                    )
                )
            )
        );

        if(!$result) {
            $this->errors[] = $this->lang('error_sending_email');
        }
        return $result;
    }
}
