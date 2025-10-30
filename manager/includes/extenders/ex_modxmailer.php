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
    var $mb_language = false;
    var $encode_header_method = null;

    function __construct()
    {
        $this->encode_header_method = '';

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

        if (evo()->config('mail_charset')) {
            $mail_charset = evo()->config('mail_charset');
        } else {
            $mail_charset = strtolower(evo()->config('manager_language'));
            if (substr($mail_charset, 0, 8) === 'japanese') {
                $mail_charset = 'jis';
            } else {
                $mail_charset = 'utf8';
            }
        }

        switch ($mail_charset) {
            case 'iso-8859-1':
                $this->CharSet = 'iso-8859-1';
                $this->Encoding = 'quoted-printable';
                $this->mb_language = 'English';
                break;
            case 'jis':
                $this->CharSet = 'ISO-2022-JP';
                $this->Encoding = '7bit';
                $this->mb_language = 'Japanese';
                $this->encode_header_method = 'mb_encode_mimeheader';
                $this->IsHTML(false);
                break;
            case 'utf8':
            case 'utf-8':
            default:
                $this->CharSet = 'UTF-8';
                $this->Encoding = 'base64';
                $this->mb_language = 'UNI';
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

    function EncodeHeader($str, $position = 'text')
    {
        if ($this->encode_header_method == 'mb_encode_mimeheader') {
            return mb_encode_mimeheader($str, $this->CharSet, 'B', "\n");
        } else {
            return parent::EncodeHeader($str, $position);
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
        $this->Body = str_replace(array_keys($target), array_values($target), $this->Body);

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

    function MailSend($header, $body)
    {
        $org_body = $body;

        switch ($this->CharSet) {
            case 'ISO-2022-JP':
                $body = mb_convert_encoding($body, 'JIS', evo()->config('modx_charset'));
                $this->Subject = $this->EncodeHeader($this->Subject);
                $mode = 'mb';
            break;
            default:
                $mode = 'normal';
        }

        if (evo()->debug) {
            $debug_info = 'CharSet = ' . $this->CharSet . "\n";
            $debug_info .= 'Encoding = ' . $this->Encoding . "\n";
            $debug_info .= 'mb_language = ' . $this->mb_language . "\n";
            $debug_info .= 'encode_header_method = ' . $this->encode_header_method . "\n";
            $debug_info .= "send_mode = {$mode}\n";
            $debug_info .= 'Subject = ' . $this->Subject . "\n";
            $log = "<pre>{$debug_info}\n{$header}\n{$org_body}</pre>";
            evo()->logEvent(1, 1, $log, 'MODxMailer debug information');
            //return true;
        }
        if ($mode === 'normal') {
            return parent::MailSend($header, $body);
        }
        return $this->mbMailSend($header, $body);
    }

    function mbMailSend($header, $body)
    {
        $to = '';
        for ($i = 0; $i < count($this->to); $i++) {
            if ($i != 0) {
                $to .= ', ';
            }
            $to .= $this->AddrFormat($this->to[$i]);
        }

        if ($this->Sender) {
            $old_from = ini_get('sendmail_from');
            ini_set('sendmail_from', $this->Sender);
        }
        $toArr = explode(',', $to);
        $params = sprintf("-oi -f %s", $this->Sender);
        foreach ($toArr as $val) {
            $rt = mail($val, $this->Subject, $body, $header, $params);
        }
        if (!empty($old_from)) {
            ini_set('sendmail_from', $old_from);
        }

        if (!$rt) {
            $this->SetError(
                implode(
                    "\n", [
                        $this->Lang('instantiate') . "<br />",
                        "{$this->Subject}<br />",
                        "{$this->FromName}&lt;{$this->From}&gt;<br />",
                        mb_convert_encoding($body, evo()->config('modx_charset'), $this->CharSet)
                    ]
                )
            );
            return false;
        }

        return true;
    }

    function SetError($msg)
    {
        global $modx;
        $modx->config['send_errormail'] = '0';

        $result = parent::SetError($msg);
        $modx->logEvent(0, 3, $this->formatErrorLogMessage($msg), 'phpmailer');

        return $result;
    }

    protected function formatErrorLogMessage($msg)
    {
        $lines = [];

        $lines[] = 'Error message: ' . $this->normalizeLineBreaks($msg);
        if (!empty($this->ErrorInfo) && $this->ErrorInfo !== $msg) {
            $lines[] = 'Detailed message: ' . $this->normalizeLineBreaks($this->ErrorInfo);
        }

        $lines[] = 'Mailer: ' . $this->Mailer;
        $lines[] = 'Subject: ' . $this->Subject;
        $lines[] = 'From: ' . $this->FromName . ' <' . $this->From . '>';

        $lines[] = 'To: ' . $this->formatAddressList($this->to);
        $lines[] = 'CC: ' . $this->formatAddressList($this->cc);
        $lines[] = 'BCC: ' . $this->formatAddressList($this->bcc);
        $lines[] = 'ReplyTo: ' . $this->formatAddressList($this->ReplyTo);

        if ($this->Mailer === 'smtp') {
            $lines[] = 'SMTP Host: ' . $this->Host;
            $lines[] = 'SMTP Port: ' . $this->Port;
            $lines[] = 'SMTP Secure: ' . ($this->SMTPSecure ?: '(none)');
            $lines[] = 'SMTP Auth: ' . ($this->SMTPAuth ? 'enabled' : 'disabled');
        } elseif ($this->Mailer === 'sendmail' || $this->Mailer === 'qmail') {
            $lines[] = 'Sendmail: ' . $this->Sendmail;
        } else {
            $lines[] = 'sendmail_path: ' . ini_get('sendmail_path');
        }

        $phpError = function_exists('error_get_last') ? error_get_last() : null;
        if ($phpError && !empty($phpError['message'])) {
            $lines[] = sprintf(
                'Last PHP error: [%s] %s in %s on line %s',
                isset($phpError['type']) ? $phpError['type'] : 'n/a',
                $phpError['message'],
                isset($phpError['file']) ? $phpError['file'] : 'n/a',
                isset($phpError['line']) ? $phpError['line'] : 'n/a'
            );
        }

        $logBody = implode("\n", $lines);
        if (function_exists('hsc')) {
            $logBody = hsc($logBody);
        } else {
            $logBody = htmlspecialchars($logBody, ENT_QUOTES, 'UTF-8');
        }

        return '<pre>' . $logBody . '</pre>';
    }

    protected function normalizeLineBreaks($text)
    {
        $text = (string)$text;
        return trim(preg_replace('/<br\s*\/?>(\r?\n)?/i', "\n", $text));
    }

    protected function formatAddressList($addresses)
    {
        if (!is_array($addresses) || empty($addresses)) {
            return '(none)';
        }

        $formatted = [];
        foreach ($addresses as $address) {
            $formatted[] = $this->addrFormat($address);
        }

        return implode(', ', $formatted);
    }

    function address_split($address)
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
