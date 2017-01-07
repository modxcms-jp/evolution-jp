<?php
class SMTP
{
    const VERSION = '5.2.21';
    const CRLF = "\r\n";
    const DEFAULT_SMTP_PORT = 25;
    const MAX_LINE_LENGTH = 998;
    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;
    const DEBUG_CONNECTION = 3;
    const DEBUG_LOWLEVEL = 4;
    public $Version = '5.2.21';
    public $SMTP_PORT = 25;
    public $CRLF = "\r\n";
    public $do_debug = self::DEBUG_OFF;
    public $Debugoutput = 'echo';
    public $do_verp = false;
    public $Timeout = 300;
    public $Timelimit = 300;
    protected $smtp_transaction_id_patterns = array('exim' => '/[0-9]{3} OK id=(.*)/', 'sendmail' => '/[0-9]{3} 2.0.0 (.*) Message/', 'postfix' => '/[0-9]{3} 2.0.0 Ok: queued as (.*)/');
    protected $smtp_conn;
    protected $error = array('error' => '', 'detail' => '', 'smtp_code' => '', 'smtp_code_ex' => '');
    protected $helo_rply = null;
    protected $server_caps = null;
    protected $last_reply = '';
    protected function edebug($str, $level = 0)
    {
        if ($level > $this->do_debug) {
            return;
        }
        if (!in_array($this->Debugoutput, array(
            'error_log',
            'html',
            'echo'
        )) and is_callable($this->Debugoutput)) {
            call_user_func($this->Debugoutput, $str, $level);
            return;
        }
        switch ($this->Debugoutput) {
            case 'error_log':
                error_log($str);
                break;
            case 'html':
                echo htmlentities(preg_replace('/[\r\n]+/', '', $str), ENT_QUOTES, 'UTF-8') . "<br>\n";
                break;
            case 'echo':
            default:
                $str = preg_replace('/(\r\n|\r|\n)/ms', "\n", $str);
                echo gmdate('Y-m-d H:i:s') . "\t" . str_replace("\n", "\n                   \t                  ", trim($str)) . "\n";
        }
    }
    public function connect($host, $port = null, $timeout = 30, $options = array())
    {
        static $streamok;
        if (is_null($streamok)) {
            $streamok = function_exists('stream_socket_client');
        }
        $this->setError('');
        if ($this->connected()) {
            $this->setError('Already connected to a server');
            return false;
        }
        if (empty($port)) {
            $port = self::DEFAULT_SMTP_PORT;
        }
        $this->edebug("Connection: opening to $host:$port, timeout=$timeout, options=" . var_export($options, true), self::DEBUG_CONNECTION);
        $errno  = 0;
        $errstr = '';
        if ($streamok) {
            $socket_context = stream_context_create($options);
            set_error_handler(array(
                $this,
                'errorHandler'
            ));
            $this->smtp_conn = stream_socket_client($host . ":" . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $socket_context);
            restore_error_handler();
        } else {
            $this->edebug("Connection: stream_socket_client not available, falling back to fsockopen", self::DEBUG_CONNECTION);
            set_error_handler(array(
                $this,
                'errorHandler'
            ));
            $this->smtp_conn = fsockopen($host, $port, $errno, $errstr, $timeout);
            restore_error_handler();
        }
        if (!is_resource($this->smtp_conn)) {
            $this->setError('Failed to connect to server', $errno, $errstr);
            $this->edebug('SMTP ERROR: ' . $this->error['error'] . ": $errstr ($errno)", self::DEBUG_CLIENT);
            return false;
        }
        $this->edebug('Connection: opened', self::DEBUG_CONNECTION);
        if (substr(PHP_OS, 0, 3) != 'WIN') {
            $max = ini_get('max_execution_time');
            if ($max != 0 && $timeout > $max) {
                @set_time_limit($timeout);
            }
            stream_set_timeout($this->smtp_conn, $timeout, 0);
        }
        $announce = $this->get_lines();
        $this->edebug('SERVER -> CLIENT: ' . $announce, self::DEBUG_SERVER);
        return true;
    }
    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }
        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }
        if (!stream_socket_enable_crypto($this->smtp_conn, true, $crypto_method)) {
            return false;
        }
        return true;
    }
    public function authenticate($username, $password, $authtype = null, $realm = '', $workstation = '', $OAuth = null)
    {
        if (!$this->server_caps) {
            $this->setError('Authentication is not allowed before HELO/EHLO');
            return false;
        }
        if (array_key_exists('EHLO', $this->server_caps)) {
            if (!array_key_exists('AUTH', $this->server_caps)) {
                $this->setError('Authentication is not allowed at this stage');
                return false;
            }
            self::edebug('Auth method requested: ' . ($authtype ? $authtype : 'UNKNOWN'), self::DEBUG_LOWLEVEL);
            self::edebug('Auth methods available on the server: ' . implode(',', $this->server_caps['AUTH']), self::DEBUG_LOWLEVEL);
            if (empty($authtype)) {
                foreach (array(
                    'CRAM-MD5',
                    'LOGIN',
                    'PLAIN',
                    'NTLM',
                    'XOAUTH2'
                ) as $method) {
                    if (in_array($method, $this->server_caps['AUTH'])) {
                        $authtype = $method;
                        break;
                    }
                }
                if (empty($authtype)) {
                    $this->setError('No supported authentication methods found');
                    return false;
                }
                self::edebug('Auth method selected: ' . $authtype, self::DEBUG_LOWLEVEL);
            }
            if (!in_array($authtype, $this->server_caps['AUTH'])) {
                $this->setError("The requested authentication method \"$authtype\" is not supported by the server");
                return false;
            }
        } elseif (empty($authtype)) {
            $authtype = 'LOGIN';
        }
        switch ($authtype) {
            case 'PLAIN':
                if (!$this->sendCommand('AUTH', 'AUTH PLAIN', 334)) {
                    return false;
                }
                if (!$this->sendCommand('User & Password', base64_encode("\0" . $username . "\0" . $password), 235)) {
                    return false;
                }
                break;
            case 'LOGIN':
                if (!$this->sendCommand('AUTH', 'AUTH LOGIN', 334)) {
                    return false;
                }
                if (!$this->sendCommand("Username", base64_encode($username), 334)) {
                    return false;
                }
                if (!$this->sendCommand("Password", base64_encode($password), 235)) {
                    return false;
                }
                break;
            case 'XOAUTH2':
                if (is_null($OAuth)) {
                    return false;
                }
                $oauth = $OAuth->getOauth64();
                if (!$this->sendCommand('AUTH', 'AUTH XOAUTH2 ' . $oauth, 235)) {
                    return false;
                }
                break;
            case 'NTLM':
                require_once 'extras/ntlm_sasl_client.php';
                $temp        = new stdClass;
                $ntlm_client = new ntlm_sasl_client_class;
                if (!$ntlm_client->initialize($temp)) {
                    $this->setError($temp->error);
                    $this->edebug('You need to enable some modules in your php.ini file: ' . $this->error['error'], self::DEBUG_CLIENT);
                    return false;
                }
                $msg1 = $ntlm_client->typeMsg1($realm, $workstation);
                if (!$this->sendCommand('AUTH NTLM', 'AUTH NTLM ' . base64_encode($msg1), 334)) {
                    return false;
                }
                $challenge = substr($this->last_reply, 3);
                $challenge = base64_decode($challenge);
                $ntlm_res  = $ntlm_client->NTLMResponse(substr($challenge, 24, 8), $password);
                $msg3      = $ntlm_client->typeMsg3($ntlm_res, $username, $realm, $workstation);
                return $this->sendCommand('Username', base64_encode($msg3), 235);
            case 'CRAM-MD5':
                if (!$this->sendCommand('AUTH CRAM-MD5', 'AUTH CRAM-MD5', 334)) {
                    return false;
                }
                $challenge = base64_decode(substr($this->last_reply, 4));
                $response  = $username . ' ' . $this->hmac($challenge, $password);
                return $this->sendCommand('Username', base64_encode($response), 235);
            default:
                $this->setError("Authentication method \"$authtype\" is not supported");
                return false;
        }
        return true;
    }
    protected function hmac($data, $key)
    {
        if (function_exists('hash_hmac')) {
            return hash_hmac('md5', $data, $key);
        }
        $bytelen = 64;
        if (strlen($key) > $bytelen) {
            $key = pack('H*', md5($key));
        }
        $key    = str_pad($key, $bytelen, chr(0x00));
        $ipad   = str_pad('', $bytelen, chr(0x36));
        $opad   = str_pad('', $bytelen, chr(0x5c));
        $k_ipad = $key ^ $ipad;
        $k_opad = $key ^ $opad;
        return md5($k_opad . pack('H*', md5($k_ipad . $data)));
    }
    public function connected()
    {
        if (is_resource($this->smtp_conn)) {
            $sock_status = stream_get_meta_data($this->smtp_conn);
            if ($sock_status['eof']) {
                $this->edebug('SMTP NOTICE: EOF caught while checking if connected', self::DEBUG_CLIENT);
                $this->close();
                return false;
            }
            return true;
        }
        return false;
    }
    public function close()
    {
        $this->setError('');
        $this->server_caps = null;
        $this->helo_rply   = null;
        if (is_resource($this->smtp_conn)) {
            fclose($this->smtp_conn);
            $this->smtp_conn = null;
            $this->edebug('Connection: closed', self::DEBUG_CONNECTION);
        }
    }
    public function data($msg_data)
    {
        if (!$this->sendCommand('DATA', 'DATA', 354)) {
            return false;
        }
        $lines      = explode("\n", str_replace(array(
            "\r\n",
            "\r"
        ), "\n", $msg_data));
        $field      = substr($lines[0], 0, strpos($lines[0], ':'));
        $in_headers = false;
        if (!empty($field) && strpos($field, ' ') === false) {
            $in_headers = true;
        }
        foreach ($lines as $line) {
            $lines_out = array();
            if ($in_headers and $line == '') {
                $in_headers = false;
            }
            while (isset($line[self::MAX_LINE_LENGTH])) {
                $pos = strrpos(substr($line, 0, self::MAX_LINE_LENGTH), ' ');
                if (!$pos) {
                    $pos         = self::MAX_LINE_LENGTH - 1;
                    $lines_out[] = substr($line, 0, $pos);
                    $line        = substr($line, $pos);
                } else {
                    $lines_out[] = substr($line, 0, $pos);
                    $line        = substr($line, $pos + 1);
                }
                if ($in_headers) {
                    $line = "\t" . $line;
                }
            }
            $lines_out[] = $line;
            foreach ($lines_out as $line_out) {
                if (!empty($line_out) and $line_out[0] == '.') {
                    $line_out = '.' . $line_out;
                }
                $this->client_send($line_out . self::CRLF);
            }
        }
        $savetimelimit   = $this->Timelimit;
        $this->Timelimit = $this->Timelimit * 2;
        $result          = $this->sendCommand('DATA END', '.', 250);
        $this->Timelimit = $savetimelimit;
        return $result;
    }
    public function hello($host = '')
    {
        return (boolean) ($this->sendHello('EHLO', $host) or $this->sendHello('HELO', $host));
    }
    protected function sendHello($hello, $host)
    {
        $noerror         = $this->sendCommand($hello, $hello . ' ' . $host, 250);
        $this->helo_rply = $this->last_reply;
        if ($noerror) {
            $this->parseHelloFields($hello);
        } else {
            $this->server_caps = null;
        }
        return $noerror;
    }
    protected function parseHelloFields($type)
    {
        $this->server_caps = array();
        $lines             = explode("\n", $this->helo_rply);
        foreach ($lines as $n => $s) {
            $s = trim(substr($s, 4));
            if (empty($s)) {
                continue;
            }
            $fields = explode(' ', $s);
            if (!empty($fields)) {
                if (!$n) {
                    $name   = $type;
                    $fields = $fields[0];
                } else {
                    $name = array_shift($fields);
                    switch ($name) {
                        case 'SIZE':
                            $fields = ($fields ? $fields[0] : 0);
                            break;
                        case 'AUTH':
                            if (!is_array($fields)) {
                                $fields = array();
                            }
                            break;
                        default:
                            $fields = true;
                    }
                }
                $this->server_caps[$name] = $fields;
            }
        }
    }
    public function mail($from)
    {
        $useVerp = ($this->do_verp ? ' XVERP' : '');
        return $this->sendCommand('MAIL FROM', 'MAIL FROM:<' . $from . '>' . $useVerp, 250);
    }
    public function quit($close_on_error = true)
    {
        $noerror = $this->sendCommand('QUIT', 'QUIT', 221);
        $err     = $this->error;
        if ($noerror or $close_on_error) {
            $this->close();
            $this->error = $err;
        }
        return $noerror;
    }
    public function recipient($address)
    {
        return $this->sendCommand('RCPT TO', 'RCPT TO:<' . $address . '>', array(
            250,
            251
        ));
    }
    public function reset()
    {
        return $this->sendCommand('RSET', 'RSET', 250);
    }
    protected function sendCommand($command, $commandstring, $expect)
    {
        if (!$this->connected()) {
            $this->setError("Called $command without being connected");
            return false;
        }
        if (strpos($commandstring, "\n") !== false or strpos($commandstring, "\r") !== false) {
            $this->setError("Command '$command' contained line breaks");
            return false;
        }
        $this->client_send($commandstring . self::CRLF);
        $this->last_reply = $this->get_lines();
        $matches          = array();
        if (preg_match("/^([0-9]{3})[ -](?:([0-9]\\.[0-9]\\.[0-9]) )?/", $this->last_reply, $matches)) {
            $code    = $matches[1];
            $code_ex = (count($matches) > 2 ? $matches[2] : null);
            $detail  = preg_replace("/{$code}[ -]" . ($code_ex ? str_replace('.', '\\.', $code_ex) . ' ' : '') . "/m", '', $this->last_reply);
        } else {
            $code    = substr($this->last_reply, 0, 3);
            $code_ex = null;
            $detail  = substr($this->last_reply, 4);
        }
        $this->edebug('SERVER -> CLIENT: ' . $this->last_reply, self::DEBUG_SERVER);
        if (!in_array($code, (array) $expect)) {
            $this->setError("$command command failed", $detail, $code, $code_ex);
            $this->edebug('SMTP ERROR: ' . $this->error['error'] . ': ' . $this->last_reply, self::DEBUG_CLIENT);
            return false;
        }
        $this->setError('');
        return true;
    }
    public function sendAndMail($from)
    {
        return $this->sendCommand('SAML', "SAML FROM:$from", 250);
    }
    public function verify($name)
    {
        return $this->sendCommand('VRFY', "VRFY $name", array(
            250,
            251
        ));
    }
    public function noop()
    {
        return $this->sendCommand('NOOP', 'NOOP', 250);
    }
    public function turn()
    {
        $this->setError('The SMTP TURN command is not implemented');
        $this->edebug('SMTP NOTICE: ' . $this->error['error'], self::DEBUG_CLIENT);
        return false;
    }
    public function client_send($data)
    {
        $this->edebug("CLIENT -> SERVER: $data", self::DEBUG_CLIENT);
        return fwrite($this->smtp_conn, $data);
    }
    public function getError()
    {
        return $this->error;
    }
    public function getServerExtList()
    {
        return $this->server_caps;
    }
    public function getServerExt($name)
    {
        if (!$this->server_caps) {
            $this->setError('No HELO/EHLO was sent');
            return null;
        }
        if (!array_key_exists($name, $this->server_caps)) {
            if ($name == 'HELO') {
                return $this->server_caps['EHLO'];
            }
            if ($name == 'EHLO' || array_key_exists('EHLO', $this->server_caps)) {
                return false;
            }
            $this->setError('HELO handshake was used. Client knows nothing about server extensions');
            return null;
        }
        return $this->server_caps[$name];
    }
    public function getLastReply()
    {
        return $this->last_reply;
    }
    protected function get_lines()
    {
        if (!is_resource($this->smtp_conn)) {
            return '';
        }
        $data    = '';
        $endtime = 0;
        stream_set_timeout($this->smtp_conn, $this->Timeout);
        if ($this->Timelimit > 0) {
            $endtime = time() + $this->Timelimit;
        }
        while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
            $str = @fgets($this->smtp_conn, 515);
            $this->edebug("SMTP -> get_lines(): \$data is \"$data\"", self::DEBUG_LOWLEVEL);
            $this->edebug("SMTP -> get_lines(): \$str is  \"$str\"", self::DEBUG_LOWLEVEL);
            $data .= $str;
            if ((isset($str[3]) and $str[3] == ' ')) {
                break;
            }
            $info = stream_get_meta_data($this->smtp_conn);
            if ($info['timed_out']) {
                $this->edebug('SMTP -> get_lines(): timed-out (' . $this->Timeout . ' sec)', self::DEBUG_LOWLEVEL);
                break;
            }
            if ($endtime and time() > $endtime) {
                $this->edebug('SMTP -> get_lines(): timelimit reached (' . $this->Timelimit . ' sec)', self::DEBUG_LOWLEVEL);
                break;
            }
        }
        return $data;
    }
    public function setVerp($enabled = false)
    {
        $this->do_verp = $enabled;
    }
    public function getVerp()
    {
        return $this->do_verp;
    }
    protected function setError($message, $detail = '', $smtp_code = '', $smtp_code_ex = '')
    {
        $this->error = array(
            'error' => $message,
            'detail' => $detail,
            'smtp_code' => $smtp_code,
            'smtp_code_ex' => $smtp_code_ex
        );
    }
    public function setDebugOutput($method = 'echo')
    {
        $this->Debugoutput = $method;
    }
    public function getDebugOutput()
    {
        return $this->Debugoutput;
    }
    public function setDebugLevel($level = 0)
    {
        $this->do_debug = $level;
    }
    public function getDebugLevel()
    {
        return $this->do_debug;
    }
    public function setTimeout($timeout = 0)
    {
        $this->Timeout = $timeout;
    }
    public function getTimeout()
    {
        return $this->Timeout;
    }
    protected function errorHandler($errno, $errmsg)
    {
        $notice = 'Connection: Failed to connect to server.';
        $this->setError($notice, $errno, $errmsg);
        $this->edebug($notice . ' Error number ' . $errno . '. "Error notice: ' . $errmsg, self::DEBUG_CONNECTION);
    }
    public function getLastTransactionID()
    {
        $reply = $this->getLastReply();
        if (empty($reply)) {
            return null;
        }
        foreach ($this->smtp_transaction_id_patterns as $smtp_transaction_id_pattern) {
            if (preg_match($smtp_transaction_id_pattern, $reply, $matches)) {
                return $matches[1];
            }
        }
        return false;
    }
}
