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

    public function run() {
        $i = event()->name;
        if ($i === 'OnManagerLoginFormRender') {
            event()->output(
                $this->showPrompt(
                    $this->getVar('action'),
                    $this->getVar('email')
                )
            );
            return;
        }

        if ($i === 'OnManagerChangePassword') {
            if (isset($_SESSION['mgrForgetPassword'])) {
                unset($_SESSION['mgrForgetPassword']);
            }
            return;
        }

        $key = $this->getVar('fmpkey');

        if ($i === 'OnManagerLoginFormPrerender') {
            $this->redirectLoginProcessor($key);
            return;
        }

        if ($i === 'OnBeforeManagerLogin') {
            $this->unBlock($key);
            return;
        }

        if ($i === 'OnManagerAuthentication') {
            event()->output($this->getAuthStatus($key));
        }
    }

    private function redirectLoginProcessor($key) {
        if(!$key) {
            return;
        }

        $user = $this->getUser($key);
        header(
            sprintf(
                'Location:%smanager/processors/login.processor.php?username=%s&fmpkey=%s%s'
                , MODX_SITE_URL
                , $user['username']
                , $key
                , evo()->config['use_captcha']==='1' ? '&captcha_code=ignore' : ''
            )
        );
        exit;
    }

    private function showPrompt($action,$to) {
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
        if (!$this->sendEmail($to)) {
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

    private function unBlock($key) {
        if(!$key) {
            return;
        }

        $user = $this->getUser($key);
        if (!isset($user['id'])) {
            $this->errors[] = $this->lang('user_doesnt_exist');
            return;
        }

        if($this->errors) {
            return;
        }
        db()->update(
            'blocked=0,blockeduntil=0,failedlogincount=0'
            , '[+prefix+]user_attributes'
            , sprintf("internalKey='%s'", $user['id'])
        );
    }

    private function getAuthStatus($key) {
        if(empty($key)) {
            return false;
        }
        $_SESSION['mgrForgetPassword'] = '1';
        if(!$this->getUser($key) || count($this->errors) != 0) {
            return false;
        }
        $captcha_code = $this->getVar('captcha_code');
        if ($captcha_code !== false) {
            $_SESSION['veriword'] = $captcha_code;
        }
        return true;
    }

    private function getForm()
    {
        return evo()->parseText(
            file_get_contents($this->tpl_path . 'form.tpl')
            , $this->lang
        );
    }

    /* Get user info including a hash unique to this user, password, and day */
    private function getUser($key='',$target='key') {
        if(!$key || !is_string($key)) {
            return false;
        }

        if ($target==='key') {
            $where = sprintf(
                "MD5(CONCAT(attr.lastlogin,usr.password))='%s'"
                , db()->escape($key)
            );
        } elseif($target==='email') {
            $where = sprintf("attr.email='%s'", db()->escape($key));
        } else {
            $where = '';
        }

        $result = db()->select(
            'usr.id, usr.username, attr.email, MD5(CONCAT(attr.lastlogin,usr.password)) AS `key`'
            , array(
                '[+prefix+]manager_users usr',
                'INNER JOIN [+prefix+]user_attributes attr ON usr.id=attr.internalKey'
            )
            , $where
            , ''
            , 1
        );
        $user = $result ? db()->getRow($result) : null;

        if(!$user) {
            $this->errors[] = $this->lang('could_not_find_user');
        }
        return $user;
    }

    /* Send an email with a link to login */
    private function sendEmail($to) {
        $user = $this->getUser($to,'email');
        if(!$user) {
            return false;
        }

        $result = evo()->sendmail(
            array(
                'subject' => $this->lang('password_change_request'),
                'sendto' => $to
            ),
            evo()->parseDocumentSource(
                evo()->parseText(
                    file_get_contents($this->tpl_path . 'sendmail.tpl')
                    , array(
                        'intro'        => $this->lang('forgot_password_email_intro'),
                        'fmpkey'       => $user['key'] . (evo()->config['use_captcha']==1 ? '&captcha_code=ignore' : ''),
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

    private function getVar($varName) {
        if(!is_string(evo()->input_get($varName))) {
            return false;
        }
        return trim(evo()->input_get($varName));
    }
}
