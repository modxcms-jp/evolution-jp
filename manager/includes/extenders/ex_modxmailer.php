<?php
/*******************************************************
 *
 * MODxMailer Class extends PHPMailer
 * Created by ZeRo (http://www.petit-power.com/)
 * updated by yama (http://kyms.jp/)
 *
 *******************************************************
 */

include_once(MODX_CORE_PATH . 'controls/phpmailer/class.phpmailer.php');

/* ------------------------------------------------------------------
 *
 * MODxMailer - Extended PHPMailer
 *
 * -----------------------------------------------------------------
 */

class MODxMailer extends PHPMailer
{
    public $mb_language = false;

    public function __construct()
    {
        if(evo()->config('email_method')==='smtp') {
            include_once MODX_CORE_PATH . 'controls/phpmailer/class.smtp.php';
            $this->IsSMTP();
            $this->Host = evo()->config('smtp_host') . ':' . evo()->config('smtp_port');
            $this->SMTPAuth = evo()->config('smtp_auth') == 1 ? true : false;
            $this->Username = evo()->config('smtp_username');
            $this->Password = evo()->config('smtppw');
            $this->SMTPSecure = evo()->config('smtp_secure');
            if (10 < strlen($this->Password)) {
                $this->Password = substr($this->Password, 0, -7);
                $this->Password = str_replace('%', '=', $this->Password);
                $this->Password = base64_decode($this->Password);
            }
        } else {
            $this->IsMail();
        }

        $this->From = evo()->config('emailsender');
        $this->Sender = evo()->config('emailsender');
        $this->FromName = evo()->config('site_name');
        $this->IsHTML(true);

        // JIS（ISO-2022-JP）での送信は廃止した。mail_charset=jis も UTF-8 で送る
        $mail_charset = strtolower((string)evo()->config('mail_charset'));
        if ($mail_charset === 'iso-8859-1') {
            $this->CharSet = 'iso-8859-1';
            $this->Encoding = 'quoted-printable';
            $this->mb_language = 'English';
        } else {
            $this->CharSet = 'UTF-8';
            $this->Encoding = 'base64';
            $this->mb_language = 'UNI';
        }
        // 従来 JIS で送っていたサイト（mail_charset=jis、または未設定の日本語管理画面）は
        // テキストメールを既定にする挙動を残す
        if ($mail_charset === 'jis'
            || ($mail_charset === '' && substr(strtolower(evo()->config('manager_language')), 0, 8) === 'japanese')) {
            $this->IsHTML(false);
        }
        if (extension_loaded('mbstring') && $this->mb_language !== false) {
            mb_language($this->mb_language);
            mb_internal_encoding(evo()->config('modx_charset'));
        }
        $exconf = MODX_CORE_PATH . 'controls/phpmailer/config.inc.php';
        if (is_file($exconf)) {
            include_once($exconf);
        }
    }

    public function Send()
    {
        $target = [
            "sanitized_by_modx& #039" => "'",
            "sanitized_by_modx& #145" => "'",
            "sanitized_by_modx& #146" => "'",
            "sanitized_by_modx& #034" => "\"",
            "sanitized_by_modx& #147" => "\"",
            "sanitized_by_modx& #148" => "\"",
            "&quot;" => "\""
        ];
        $this->normalizeLegacyJis();
        $this->Body = str_replace(array_keys($target), array_values($target), $this->Body);

        $this->logSendRequest();

        try {
            if (!$this->PreSend()) {
                return false;
            }
            return $this->PostSend();
        } catch (phpmailerException $e) {
            $this->mailHeader = '';
            $this->SetError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            return false;
        }
    }

    /**
     * 呼び出し側が JIS 前提で組み立てたメールを UTF-8 に揃える。
     * cfFormMailer など、本文を自前で ISO-2022-JP に変換し Encoding=7bit を指定してくる
     * 拡張機能への互換処理。
     */
    private function normalizeLegacyJis()
    {
        if (stripos($this->CharSet, 'ISO-2022-JP') === 0) {
            $this->CharSet = 'UTF-8';
        }
        if (strtoupper($this->CharSet) !== 'UTF-8') {
            return;
        }
        foreach (['Subject', 'FromName', 'Body', 'AltBody', 'Ical'] as $prop) {
            $this->$prop = $this->jisToUtf8($this->$prop);
        }
        foreach ($this->attachment as $i => $attachment) {
            $this->attachment[$i][2] = $this->jisToUtf8($attachment[2]);
        }
        // UTF-8 の 8bit 文字は 7bit では送れない
        if ($this->Encoding === '7bit'
            && ($this->has8bitChars($this->Body) || $this->has8bitChars($this->AltBody))) {
            $this->Encoding = 'base64';
        }
    }

    private function jisToUtf8($str)
    {
        if (!is_string($str) || strpos($str, "\x1b") === false) {
            return $str;
        }
        // ISO-2022-JP のエスケープシーケンス（ESC $ @ / ESC $ B / ESC ( B / ESC ( J / ESC ( I）
        if (!preg_match('/\x1b(?:\$[@B]|\([BJI])/', $str)) {
            return $str;
        }
        return mb_convert_encoding($str, 'UTF-8', 'ISO-2022-JP-MS');
    }

    public function MailSend($header, $body)
    {
        if (evo()->debug) {
            $debug_info = 'CharSet = ' . $this->CharSet . "\n";
            $debug_info .= 'Encoding = ' . $this->Encoding . "\n";
            $debug_info .= 'mb_language = ' . $this->mb_language . "\n";
            $debug_info .= 'Subject = ' . $this->Subject . "\n";
            $log = "<pre>{$debug_info}\n{$header}\n{$body}</pre>";
            evo()->logEvent(1, 1, $log, 'MODxMailer debug information');
        }
        return parent::MailSend($header, $body);
    }

    private function logSendRequest()
    {
        if (!config('modxmailer_log', 0)) {
            return;
        }

        $logData = [
            'from' => $this->formatFromAddress(),
            'sender' => $this->Sender,
            'reply_to' => $this->formatAddressList($this->ReplyTo),
            'to' => $this->formatAddressList($this->to),
            'cc' => $this->formatAddressList($this->cc),
            'bcc' => $this->formatAddressList($this->bcc),
            'subject' => $this->Subject,
            'post' => postv(),
        ];

        $subject = trim((string)$this->Subject);
        if ($subject === '') {
            $subject = '(no subject)';
        }
        $toSummary = implode(', ', $logData['to']);
        if ($toSummary === '') {
            $toSummary = '(no recipients)';
        }
        $summary = 'subject: ' . $subject . ' to: ' . $toSummary;

        evo()->logEvent(1, 1, $summary, 'MODxMailer request log', [
            'mail' => $logData,
        ]);
    }

    private function formatFromAddress()
    {
        if (!$this->From) {
            return '';
        }

        return $this->AddrFormat([$this->From, $this->FromName]);
    }

    private function formatAddressList($addresses)
    {
        if (!is_array($addresses) || empty($addresses)) {
            return [];
        }

        $result = [];
        foreach ($addresses as $address) {
            $result[] = $this->AddrFormat($address);
        }

        return $result;
    }

    public function SetError($msg)
    {
        global $modx;
        $modx->config['send_errormail'] = '0';
        $modx->logEvent(0, 3, $msg, 'phpmailer');
        return parent::SetError($msg);
    }

    public function address_split($address)
    {
        $address = trim($address);
        if (strpos($address, '<') !== false && substr($address, -1) === '>') {
            $address = rtrim($address, '>');
            [$name, $address] = explode('<', $address);
        } else {
            $name = '';
        }
        $result = [$name, $address];
        return $result;
    }

    public static function validateAddress($address, $patternselect = null)
    {
        global $modx;

        if (!isset($modx->config['validate_emailaddr'])) {
            $modx->config['validate_emailaddr'] = 'deny_quoted_string';
        }
        $address = trim($address);
        $localPart = substr($address, 0, strrpos($address, '@'));
        $isQuotedString = (substr($localPart, 0, 1) === '"' && substr($localPart, -1) === '"');
        switch ($modx->config('validate_emailaddr')) {
            case 'deny_quoted_string':
                if ($isQuotedString) {
                    return false;
                }
                break;
            case 'allow_quoted_string':
                if (strpos($localPart, ' -X') !== false) {
                    return false;
                } elseif (strpos($localPart, '\\') !== false) {
                    return false;
                }
                break;
        }
        return parent::validateAddress($address, $patternselect);
    }
}
