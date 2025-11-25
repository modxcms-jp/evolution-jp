<?php

trait DocumentParserSubParserTrait
{
    function sendmail($params = [], $msg = '')
    {
        global $modx;
        $p = [];
        if (isset($params) && is_string($params)) {
            if (strpos($params, '=') === false) {
                if (strpos($params, '@') !== false) {
                    $p['to'] = $params;
                } else {
                    $p['subject'] = $params;
                }
            } else {
                $params_array = explode(',', $params);
                foreach ($params_array as $k => $v) {
                    $k = trim($k);
                    $p[$k] = trim($v);
                }
            }
        } else {
            $p = $params;
        }
        if (isset($p['sendto'])) {
            $p['to'] = $p['sendto'];
        }

        if (isset($p['to']) && preg_match('@^[1-9][0-9]*$@', $p['to'])) {
            $userinfo = evo()->getUserInfo($p['to']);
            $p['to'] = $userinfo['email'];
        }
        if (isset($p['from']) && preg_match('@^[0-9]+$@', $p['from'])) {
            $userinfo = evo()->getUserInfo($p['from']);
            $p['from'] = $userinfo['email'];
            $p['fromname'] = $userinfo['username'];
        }
        if ($msg === '' && !isset($p['body'])) {
            $p['body'] = evo()->server('REQUEST_URI') . "\n" . evo()->server('HTTP_USER_AGENT') . "\n" . evo()->server('HTTP_REFERER');
        } elseif (is_string($msg) && 0 < strlen($msg)) {
            $p['body'] = $msg;
        }

        evo()->loadExtension('MODxMailer');
        $sendto = $p['to'] ?? evo()->config('emailsender');
        $sendto = explode(',', $sendto);
        foreach ($sendto as $address) {
            [$name, $address] = $modx->mail->address_split($address);
            $modx->mail->AddAddress($address, $name);
        }
        if (isset($p['cc'])) {
            $p['cc'] = explode(',', $p['cc']);
            foreach ($p['cc'] as $address) {
                [$name, $address] = $modx->mail->address_split($address);
                $modx->mail->AddCC($address, $name);
            }
        }
        if (isset($p['bcc'])) {
            $p['bcc'] = explode(',', $p['bcc']);
            foreach ($p['bcc'] as $address) {
                [$name, $address] = $modx->mail->address_split($address);
                $modx->mail->AddBCC($address, $name);
            }
        }
        if (isset($p['replyto'])) {
            [$name, $address] = $modx->mail->address_split($p['replyto']);
            $modx->mail->addReplyTo($address, $name);
        }

        if (isset($p['from']) && strpos($p['from'], '<') !== false && substr($p['from'], -1) === '>') {
            [$p['fromname'], $p['from']] = $modx->mail->address_split($p['from']);
        }
        $modx->mail->From = $p['from'] ?? evo()->config('emailsender');
        $modx->mail->FromName = $p['fromname'] ?? evo()->config('site_name');
        $modx->mail->Subject = $p['subject'] ?? evo()->config('emailsubject');
        $modx->mail->Body = $p['body'];
        if (isset($p['type']) && $p['type'] === 'text') {
            $modx->mail->IsHTML(false);
        }
        return $modx->mail->send();
    }

    function rotate_log($target = 'event_log', $limit = 2000, $trim = 100)
    {
        if ($limit < $trim) {
            $trim = $limit;
        }

        $count = db()->getValue(db()->select('COUNT(id)', "[+prefix+]{$target}"));
        $over = $count - $limit;
        if (0 < $over) {
            $trim = ($over + $trim);
            db()->delete("[+prefix+]{$target}", '', '', $trim);
        }
        if (config('automatic_optimize') == 1) {
            $rs = db()->query(
                sprintf('SHOW TABLE STATUS FROM `%s`', trim(db()->dbname, '`'))
            );
            while ($row = db()->getRow($rs)) {
                db()->query('OPTIMIZE TABLE ' . $row['Name']);
            }
        }
    }

    function addLog($title = 'no title', $msg = '', $type = 1)
    {
        if ($title === '') {
            $title = 'no title';
        }
        if (is_array($msg)) {
            $msg = sprintf('<pre>%s</pre>', print_r($msg, true));
        }
        $this->logEvent(
            0,
            $type,
            $msg ? $msg : serverv('REQUEST_URI'),
            $title
        );
    }

    function logEvent($evtid, $type, $msg, $title = 'Parser')
    {
        global $modx;
        if (!db()->isConnected()) {
            return;
        }
        if (!$modx->config) {
            $modx->getSettings();
        }
        $evtid = (int)$evtid;
        $type = (int)$type;
        if ($type < 1) {
            $type = 1;
        } // Types: 1 = information, 2 = warning, 3 = error
        if (3 < $type) {
            $type = 3;
        }
        if (db()->isConnected()) {
            $msg = db()->escape($msg);
        }
        $title = hsc($title);
        if (db()->isConnected()) {
            $title = db()->escape($title);
        }
        if (function_exists('mb_substr')) {
            $title = mb_substr($title, 0, 100, $modx->config('modx_charset', 'utf-8'));
        } else {
            $title = substr($title, 0, 100);
        }
        $LoginUserID = evo()->getLoginUserID();
        if (!$LoginUserID) {
            $LoginUserID = '0';
        }

        $fields['eventid'] = $evtid;
        $fields['type'] = $type;
        $fields['createdon'] = request_time();
        $fields['source'] = $title;
        $fields['description'] = $msg;
        $fields['user'] = $LoginUserID;
        $_ = db()->lastQuery;
        if (db()->isConnected()) {
            $insert_id = db()->insert($fields, '[+prefix+]event_log');
        } else {
            $title = 'DB connect error';
        }
        $modx->db->lastQuery = $_;
        if (config('send_errormail') && config('send_errormail') <= $type) {
            $body['URL'] = MODX_SITE_URL . ltrim(evo()->server('REQUEST_URI'), '/');
            $body['Source'] = $fields['source'];
            $body['IP'] = evo()->server('REMOTE_ADDR');
            if (evo()->server('REMOTE_ADDR')) {
                $hostname = gethostbyaddr(evo()->server('REMOTE_ADDR'));
            }
            if ($hostname) {
                $body['Host name'] = $hostname;
            }
            if ($modx->event->activePlugin) {
                $body['Plugin'] = $modx->event->activePlugin;
            }
            if ($modx->currentSnippet) {
                $body['Snippet'] = $modx->currentSnippet;
            }
            $subject = 'Error mail from ' . evo()->config('site_name');
            foreach ($body as $k => $v) {
                $mailbody[] = sprintf('[%s] %s', $k, $v);
            }
            $mailbody = implode("\n", $mailbody);
            $modx->sendmail($subject, $mailbody);
        }
        if (!isset($insert_id) || !$insert_id) {
            exit('Error while inserting event log into database.');
        }

        $trim = (int)evo()->config('event_log_trim', 100);
        if (($insert_id % $trim) == 0) {
            $limit = (int)evo()->config('event_log_limit', 2000);
            $modx->rotate_log('event_log', $limit, $trim);
        }
    }

    function clearCache($params = [])
    {
        global $modx;

        if (!is_array($params) && preg_match('@^[1-9][0-9]*$@', $params)) {
            $docid = $params;
            if ($modx->config('cache_type') == 2) {
                $url = evo()->config('base_url') . $modx->makeUrl($docid, '', '', 'root_rel');
                $filename = hash('crc32b', $url);
            } else {
                $filename = "docid_{$docid}";
            }

            $_ = ['pages', 'pc', 'smartphone', 'tablet', 'mobile'];
            foreach ($_ as $uaType) {
                $page_cache_path = MODX_CACHE_PATH . "{$uaType}/{$filename}.pageCache.php";
                if (is_file($page_cache_path)) {
                    unlink($page_cache_path);
                }
            }
        }

        if (opendir(MODX_CACHE_PATH) === false) {
            return false;
        }

        if (is_string($params) && $params === 'full') {
            $params = [];
            $params['showReport'] = false;
            $params['target'] = 'pagecache,sitecache';
        }

        $showReport = !empty($params['showReport']) ? $params['showReport'] : false;
        $target = !empty($params['target']) ? $params['target'] : 'pagecache,sitecache';

        include_once MODX_CORE_PATH . 'cache_sync.class.php';
        $sync = new synccache();
        $sync->setCachepath(MODX_CACHE_PATH);
        $sync->setReport($showReport);
        $sync->setTarget($target);
        $sync->emptyCache(); // first empty the cache

        return true;
    }

    function messageQuit(
        $msg = 'unspecified error',
        $query = '',
        $is_error = true,
        $nr = '',
        $file = '',
        $source = '',
        $text = '',
        $line = '',
        $output = ''
    )
    {
        global $modx;

        if (!$modx->error_reporting) {
            return true;
        }

        $version = globalv('version', '');
        $release_date = globalv('release_date', '');
        $ua = hsc(serverv('HTTP_USER_AGENT', ''));
        $referer = hsc(serverv('HTTP_REFERER', ''));
        if ($is_error) {
            $str = '<h3 style="color:red">&laquo; MODX Parse Error &raquo;</h3>
                    <table border="0" cellpadding="1" cellspacing="0">
                    <tr><td colspan="2">MODX encountered the following error while attempting to parse the requested resource:</td></tr>
                    <tr><td colspan="2"><b style="color:red;">&laquo; ' . $msg . ' &raquo;</b></td></tr>';
        } else {
            $str = '<h3 style="color:#003399">&laquo; MODX Debug/ stop message &raquo;</h3>
                    <table border="0" cellpadding="1" cellspacing="0">
                    <tr><td colspan="2">The MODX parser recieved the following debug/ stop message:</td></tr>
                    <tr><td colspan="2"><b style="color:#003399;">&laquo; ' . $msg . ' &raquo;</b></td></tr>';
        }

        $codetpl = '<tr><td colspan="2"><div style="font-weight:bold;border:1px solid #ccc;padding:8px;color:#333;background-color:#ffffcd;">[+code+]</div></td></tr>';

        if ($query) {
            $str .= parseText($codetpl, ['code' => $query]);
        }

        $errortype = [
            E_ERROR => "ERROR",
            E_WARNING => "WARNING",
            E_PARSE => "PARSING ERROR",
            E_NOTICE => "NOTICE",
            E_CORE_ERROR => "CORE ERROR",
            E_CORE_WARNING => "CORE WARNING",
            E_COMPILE_ERROR => "COMPILE ERROR",
            E_COMPILE_WARNING => "COMPILE WARNING",
            E_USER_ERROR => "USER ERROR",
            E_USER_WARNING => "USER WARNING",
            E_USER_NOTICE => "USER NOTICE",
            E_DEPRECATED => "DEPRECATED",
            E_USER_DEPRECATED => "USER DEPRECATED"
        ];

        $tpl = '<tr><td valign="top">[+left+]</td><td>[+right+]</td></tr>';
        if ($nr || $file) {
            $str .= '<tr><td colspan="2"><b>PHP error debug</b></td></tr>';
            if ($text != '') {
                $str .= $modx->parseText($codetpl, ['code' => "Error : {$text}"]);
            }
            if ($output != '') {
                $str .= $modx->parseText($codetpl, ['code' => $output]);
            }
            if (!isset($errortype[$nr])) {
                $errortype[$nr] = '';
            }
            $str .= $modx->parseText(
                $tpl,
                [
                    'left' => 'ErrorType[num] : ',
                    'right' => sprintf('%s[%s]', $errortype[$nr], $nr)
                ]
            );
            $str .= $modx->parseText(
                $tpl,
                [
                    'left' => 'File : ',
                    'right' => $file
                ]
            );
            $str .= $modx->parseText(
                $tpl,
                [
                    'left' => 'Line : ',
                    'right' => $line
                ]
            );
        }

        if ($source != '') {
            $str .= $modx->parseText(
                $tpl,
                [
                    'left' => 'Source : ',
                    'right' => $source
                ]
            );
        } elseif ($modx->currentSnippetCode) {
            $lines = explode("\n", $modx->currentSnippetCode);
            $str .= $modx->parseText(
                $tpl,
                [
                    'left' => 'Source : ',
                    'right' => sprintf(
                        '[[%s]]: %s', $modx->currentSnippet, $lines[$line - 1]
                    )
                ]
            );
        }

        if (!empty($modx->currentErrorContext)) {
            $context = $modx->currentErrorContext;
            $contextParts = [];
            if (!empty($context['type'])) {
                $contextParts[] = $context['type'];
            }
            if (!empty($context['name'])) {
                if (!empty($contextParts)) {
                    $contextParts[count($contextParts) - 1] .= ' - ' . $context['name'];
                } else {
                    $contextParts[] = $context['name'];
                }
            }
            if ($contextParts) {
                $str .= $modx->parseText(
                    $tpl,
                    [
                        'left' => 'Execution Context : ',
                        'right' => implode('', $contextParts)
                    ]
                );
            }

            $requested = $context['requested'] ?? 'inherit';
            $effective = $context['effective'] ?? 'n/a';
            $globalLevel = $context['global'] ?? 'n/a';
            $str .= $modx->parseText(
                $tpl,
                [
                    'left' => 'PHP Error Level : ',
                    'right' => sprintf(
                        'requested: %s / effective: %s / global: %s',
                        $requested,
                        $effective,
                        $globalLevel
                    )
                ]
            );

            if (!empty($context['compatibility'])) {
                $str .= $modx->parseText(
                    $tpl,
                    [
                        'left' => 'Compatibility Mode : ',
                        'right' => 'enabled'
                    ]
                );
            }
        }

        if (db()->lastQuery) {
            $str .= $modx->parseText(
                $tpl, [
                    'left' => 'LastQuery : ',
                    'right' => $modx->hsc(db()->lastQuery)
                ]
            );
        }

        $str .= '<tr><td colspan="2"><b>Basic info</b></td></tr>';

        $str .= '<tr><td valign="top" style="white-space:nowrap;">REQUEST_URI : </td>';
        $str .= sprintf('<td>%s</td>', hsc(urldecode(evo()->server('REQUEST_URI'))));
        $str .= '</tr>';

        $action = getv('a', postv('a'));

        if (isset($action) && $action) {
            include_once(MODX_CORE_PATH . 'actionlist.inc.php');
            global $action_list;
            if (isset($action_list[$action])) {
                $actionName = " - {$action_list[$action]}";
            } else {
                $actionName = '';
            }
            $str .= $modx->parseText(
                $tpl,
                [
                    'left' => 'Manager action : ',
                    'right' => $action . $actionName
                ]
            );
        }

        if (!is_null($modx->documentIdentifier) && preg_match('@^[0-9]+@', $modx->documentIdentifier)) {
            $resource = $modx->getDocumentObject('id', $modx->documentIdentifier);
            $str .= $modx->parseText(
                $tpl,
                [
                    'left' => 'Resource : ',
                    'right' => sprintf(
                        '[%s]%s',
                        $modx->documentIdentifier,
                        sprintf(
                            '<a href="%s" target="_blank">%s</a>',
                            $modx->makeUrl($modx->documentIdentifier),
                            $resource['pagetitle']
                        )
                    )
                ]
            );
        }

        if ($modx->event->activePlugin) {
            $str .= $modx->parseText(
                $tpl,
                [
                    'left' => 'Current Plugin : ',
                    'right' => sprintf('%s(%s)', $modx->event->activePlugin, $modx->event->name)
                ]
            );
        }

        $str .= $modx->parseText($tpl, ['left' => 'Referer : ', 'right' => $referer]);
        $str .= $modx->parseText($tpl, ['left' => 'User Agent : ', 'right' => $ua]);
        $str .= $modx->parseText($tpl, ['left' => 'IP : ', 'right' => $_SERVER['REMOTE_ADDR']]);

        $str .= '<tr><td colspan="2"><b>Benchmarks</b></td></tr>';

        $str .= $modx->parseText(
            $tpl,
            [
                'left' => 'MySQL : ',
                'right' => '[^qt^] ([^q^] Requests)'
            ]
        );
        $str .= $modx->parseText(
            $tpl,
            [
                'left' => 'PHP : ',
                'right' => '[^p^]'
            ]
        );
        $str .= $modx->parseText(
            $tpl,
            [
                'left' => 'Total : ',
                'right' => '[^t^]'
            ]
        );
        $str .= $modx->parseText(
            $tpl,
            [
                'left' => 'Memory : ',
                'right' => '[^m^]'
            ]
        );

        $str .= "</table>\n";

        $str = $modx->mergeBenchmarkContent($str);

        $last_error = error_get_last();
        if ($last_error) {
            $str = "<b>" . print_r($last_error, true) . "</b><br />\n{$str}";
        }
        $str .= '<br />' . $modx->get_backtrace() . "\n";


        // Log error
        if ($modx->currentSnippet) {
            $title = 'Snippet - ' . $modx->currentSnippet;
        } elseif ($modx->event->activePlugin) {
            $title = 'Plugin - ' . $modx->event->activePlugin;
        } elseif (isset($title) && $title !== '') {
            $title = 'Parser - ' . $text ? $text : $source;
        } elseif ($query !== '') {
            $title = 'SQL Query';
        } else {
            $title = 'Parser';
        }

        if (isset($actionName) && $actionName) {
            $title .= $actionName;
        }
        if ($line) {
            $title .= ' line:' . $line;
        }

        if (!empty($modx->currentErrorContext['compatibility'])) {
            $effective = $modx->currentErrorContext['effective'] ?? '';
            if ($effective !== '') {
                $title .= ' [compatibility mode: ' . $effective . ']';
            } else {
                $title .= ' [compatibility mode]';
            }
        }

        switch ($nr) {
            case E_DEPRECATED :
            case E_USER_DEPRECATED :
            case E_NOTICE :
            case E_USER_NOTICE :
                $error_level = 2;
                break;
            default:
                $error_level = 3;
        }
        $modx->logEvent(0, $error_level, $str, $title);

        // Set 500 response header
        if (2 < $error_level && $modx->event->name !== 'OnWebPageComplete') {
            if (!headers_sent()) {
                header('HTTP/1.1 500 Internal Server Error');
            }
        }

        // Display error
        if (evo()->isLoggedin()) {
            if ($modx->event->name !== 'OnWebPageComplete') {
                echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">';
                echo sprintf('<html><head><title>MODX Content Manager %s &raquo; %s</title>', $version, $release_date);
                echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
                echo sprintf('<link rel="stylesheet" type="text/css" href="%smanager/media/style/%s/style.css" />',
                MODX_SITE_URL, evo()->config('manager_theme'));
                echo '<style type="text/css">body { padding:10px; } td {font:inherit;}</style>';
                echo '</head><body>';
            }
            echo '<div style="text-align:left;">' . $str . '</div>';
            if ($modx->event->name !== 'OnWebPageComplete') {
                echo '</body></html>';
            }
        } else {
            echo 'Error';
        }
        ob_end_flush();

        exit;
    }

    function recDebugInfo()
    {
        global $modx;

        $incs = get_included_files();
        $backtrace = array_reverse(debug_backtrace());
        $i = 0;
        foreach ($incs as $v) {
            $incs[$i] = str_replace('\\', '/', $v);
            $i++;
        }
        $i = 0;
        foreach ($backtrace as $v) {
            if (isset($v['object'])) {
                unset($backtrace[$i]['object']);
            }
            if (isset($v['file'])) {
                $backtrace[$i]['file'] = str_replace('\\', '/', $v['file']);
            }
            if (isset($v['args']) && empty($v['args'])) {
                unset($backtrace[$i]['args']);
            }
            if ($v['class'] === 'DocumentParser' && $v['type'] === '->') {
                unset($backtrace[$i]['file']);
                unset($backtrace[$i]['class']);
                unset($backtrace[$i]['type']);
                $backtrace[$i]['function'] = '$modx->' . $v['function'] . '()';
            } elseif (isset($v['class'])) {
                if (strpos($v['file'], 'document.parser.class.inc.php') !== false) {
                    unset($backtrace[$i]['file']);
                }
                unset($backtrace[$i]['class']);
                unset($backtrace[$i]['type']);
                $backtrace[$i]['function'] = $v['class'] . $v['type'] . $v['function'] . '()';
            }
            $i++;
        }

        $tend = $modx->getMicroTime();
        $totaltime = $tend - $modx->tstart;
        $totaltimemsg = sprintf('Total time %2.4f s', $totaltime);
        $info['request_uri'] = $modx->decoded_request_uri;
        if (isset($modx->documentIdentifier)) {
            $info['docid'] = $modx->documentIdentifier;
        }
        $info['Total time'] = $totaltimemsg;
        $info['included_files'] = print_r($incs, true);
        $info['backtrace'] = print_r($backtrace, true);
        $info['functions'] = print_r($modx->functionLog, true);
        $msg = '<pre>' . print_r($info, true) . '</pre>';
        $this->addLog('Debug log', $msg, 1);
    }

    function get_backtrace()
    {
        global $modx;
        $str = "<p><b>Backtrace</b></p>\n";
        $str .= '<table>';
        $backtrace = array_reverse(debug_backtrace());
        foreach ($backtrace as $key => $val) {
            $key++;
            if (substr($val['function'], 0, 11) === 'messageQuit') {
                break;
            }
            if (substr($val['function'], 0, 8) === 'phpError') {
                break;
            }
            $path = str_replace('\\', '/', $val['file']);
            if (strpos($path, MODX_BASE_PATH) === 0) {
                $path = substr($path, strlen(MODX_BASE_PATH));
            }
            switch (array_get($val, 'type')) {
                case '->':
                case '::':
                    if ($val['class'] === 'DocumentParser' && $val['type'] === '->') {
                        $val['class'] = '$modx';
                    }
                    $functionName = $val['function'] = $val['class'] . $val['type'] . $val['function'];
                    break;
                default:
                    $functionName = $val['function'];
            }
            if ($functionName === 'evalSnippet' && $modx->currentSnippet) {
                $functionName .= sprintf('(%s)', $modx->currentSnippet);
            }
            $str .= '<tr><td valign="top">' . $key . "</td>";
            $str .= sprintf(
                '<td>%s()<br />%s on line %s</td>',
                $functionName,
                $path,
                $val['line']
            );
        }
        $str .= '</table>';
        return $str;
    }

    function sendRedirect($url = '', $count_attempts = 0, $type = 'REDIRECT_HEADER', $responseCode = '')
    {
        global $modx;

        if ($modx->debug) {
            register_shutdown_function([& $modx, 'recDebugInfo']);
        }

        if ($type === 'REDIRECT_HEADER') {
            $modx->config['xhtml_urls'] = 0;
        }

        if (empty($url)) {
            $url = $modx->makeUrl($modx->documentIdentifier, '', '', 'full');
        } elseif (preg_match('@^[1-9][0-9]*$@', $url)) {
            $url = $modx->makeUrl($url, '', '', 'full');
        } else {
            if (strpos($url, '[') !== false || strpos($url, '{{') !== false) {
                $url = $modx->parseDocumentSource($url);
            }

            if (strpos($url, '?') === 0) {
                $url = $modx->makeUrl($modx->documentIdentifier, '', $url, 'full');
            } elseif (preg_match('@^[1-9][0-9]*$@', $url)) {
                $url = $modx->makeUrl($url);
            } elseif (preg_match('@^[1-9][0-9]*\?@', $url)) {
                [$url, $args] = explode('?', $url, 2);
                $url = $modx->makeUrl($url, '', $args, 'full');
            }

            if (strpos($url, '[~') !== false) {
                $url = $modx->rewriteUrls($url);
            }

        }

        if ($count_attempts == 1) {
            // append the redirect count string to the url
            $currentNumberOfRedirects = isset ($_REQUEST['err']) ? $_REQUEST['err'] : 0;
            if ($currentNumberOfRedirects > 3) {
                $modx->messageQuit(
                    "Redirection attempt failed - please ensure the document you're trying to redirect to exists. <p>Redirection URL: <i>{$url}</i></p>"
                );
                exit;
            }

            $currentNumberOfRedirects += 1;
            if (strpos($url, '?') > 0) {
                $url .= '&';
            } else {
                $url .= '?';
            }
            $url .= "err={$currentNumberOfRedirects}";
        }
        if ($type === 'REDIRECT_REFRESH') {
            $header = "Refresh: 0;URL={$url}";
        } elseif ($type !== 'REDIRECT_META') {
            // check if url has /$base_url
            global $base_url, $site_url;
            if (substr($url, 0, 2) !== '//' && substr($url, 0, strlen($base_url)) == $base_url) {
                // append $site_url to make it work with Location:
                $url = $site_url . substr($url, strlen($base_url));
            }
            if (strpos($url, "\n") === false) {
                $header = 'Location: ' . $url;
            } else {
                $modx->messageQuit('No newline allowed in redirect url.');
            }
        } else {
            echo '<META HTTP-EQUIV="Refresh" CONTENT="0; URL=' . $url . '" />';
            exit;
        }

        if (strpos($responseCode, '301') !== false) {
            header($header, true, 301);
            exit;
        }
        if (strpos($responseCode, '302') !== false) {
            header($header, true, 302);
            exit;
        }
        if (strpos($responseCode, '303') !== false) {
            header($header, true, 303);
            exit;
        }
        if (strpos($responseCode, '307') !== false) {
            header($header, true, 307);
            exit;
        }
        if ($responseCode) {
            header($header, true, $responseCode);
            exit;
        }
        header($header);
        exit;
    }

    function sendForward($id, $responseCode = '')
    {
        global $modx;

        if ($modx->forwards) {
            $modx->forwards--;
            $modx->documentIdentifier = $id;
            if ($responseCode) {
                header($responseCode);
            }
            echo $modx->prepareResponse();
            exit;
        }
        $modx->messageQuit("Internal Server Error id={$id}");
        header('HTTP/1.0 500 Internal Server Error');
        echo '<h1>ERROR: Too many forward attempts!</h1><p>The request could not be completed due to too many unsuccessful forward attempts.</p>';
        exit;
    }

    function sendUnavailablePage()
    {
        global $modx;

        $dist = evo()->config('site_unavailable_page') ?: evo()->config('site_start');

        $modx->http_status_code = '503';
        $modx->sendForward($dist, 'HTTP/1.0 503 Service Unavailable');
    }

    function sendErrorPage()
    {
        global $modx;

        evo()->invokeEvent('OnPageNotFound');

        $modx->http_status_code = '404';
        evo()->sendForward(
            evo()->config('error_page') ? evo()->config('error_page') : evo()->config('site_start'),
            evo()->server('SERVER_PROTOCOL', '') . ' 404 Not Found'
        );
    }

    function sendUnauthorizedPage()
    {
        global $modx;

        // invoke OnPageUnauthorized event
        $_REQUEST['refurl'] = $modx->documentIdentifier;
        evo()->invokeEvent('OnPageUnauthorized');

        if (evo()->config('unauthorized_page')) {
            $dist = evo()->config('unauthorized_page');
        } elseif (evo()->config('error_page')) {
            $dist = evo()->config('error_page');
        } else {
            $dist = evo()->config('site_start');
        }
        $modx->http_status_code = '403';
        $modx->sendForward($dist, 'HTTP/1.1 403 Forbidden');
    }

    function getSnippetId()
    {
        global $modx;
        if ($modx->currentSnippet) {
            $snip = db()->escape($modx->currentSnippet);
            $rs = db()->select('id', '[+prefix+]site_snippets', "name='{$snip}'", '', 1);
            $row = @ db()->getRow($rs);
            if ($row['id']) {
                return $row['id'];
            }
        }
        return 0;
    }

    function getSnippetName()
    {
        return evo()->currentSnippet;
    }

    # Change current web user's password - returns true if successful, oterhwise return error message
    function changeWebUserPassword($oldPwd, $newPwd)
    {
        if ($_SESSION['webValidated'] != 1) {
            return false;
        }

        $uid = evo()->getLoginUserID();
        $ds = db()->select('id,username,password', '[+prefix+]web_users', "`id`='{$uid}'");
        $total = db()->count($ds);
        if ($total != 1) {
            return false;
        }

        $row = db()->getRow($ds);
        if ($row['password'] == md5($oldPwd)) {
            if (strlen($newPwd) < 6) {
                return 'Password is too short!';
            }
            if ($newPwd == '') {
                return "You didn't specify a password for this user!";
            }

            $f = [];
            $f['password'] = md5($newPwd);
            $f['cachepwd'] = '';
            $f = db()->escape($f);
            db()->update($f, '[+prefix+]web_users', "id='{$uid}'");
            db()->update(
                "blockeduntil='0'",
                '[+prefix+]web_user_attributes',
                "internalKey='" . $uid . "'"
            );
            // invoke OnWebChangePassword event
            $tmp = [
                'userid' => $row['id'],
                'username' => $row['username'],
                'userpassword' => $newPwd
            ];
            evo()->invokeEvent('OnWebChangePassword', $tmp);
            return true;
        }

        return 'Incorrect password.';
    }

    # add an event listner to a plugin - only for use within the current execution cycle
    function addEventListener($evtName, $pluginName)
    {
        global $modx;
        if (!$evtName || !$pluginName) {
            return false;
        }

        if (!isset($modx->pluginEvent[$evtName])) {
            $modx->pluginEvent[$evtName] = [];
        }
        return array_push($modx->pluginEvent[$evtName], $pluginName); // return array count
    }

    # remove event listner - only for use within the current execution cycle
    function removeEventListener($evtName, $pluginName = '')
    {
        global $modx;

        if (!$evtName) {
            return false;
        }

        if ($pluginName == '') {
            unset ($modx->pluginEvent[$evtName]);
            return true;
        }

        foreach ($modx->pluginEvent[$evtName] as $key => $val) {
            if ($modx->pluginEvent[$evtName][$key] == $pluginName) {
                unset ($modx->pluginEvent[$evtName][$key]);
                return true;
            }
        }

        return false;
    }

    function regClientCSS($src, $media = '')
    {
        global $modx;

        if (empty($src) || isset ($modx->loadedjscripts[$src])) {
            return;
        }

        $nextpos = max(array_merge([0], array_keys($modx->sjscripts))) + 1;

        $modx->loadedjscripts[$src] = [
            'startup' => true,
            'version' => '0',
            'pos' => $nextpos
        ];

        if (strpos(strtolower($src), '<style') !== false
                || strpos(strtolower($src), '<link') !== false) {
            $modx->sjscripts[$nextpos] = $src;
            return;
        }

        $modx->sjscripts[$nextpos] = sprintf(
            '<link rel="stylesheet" type="text/css" href="%s" %s/>',
            $src,
            $media ? sprintf('media="%s" ', $media) : ''
        );
    }

    # Registers Client-side JavaScript     - these scripts are loaded at the end of the page unless $startup is true
    function regClientScript(
        $src,
        $options = ['name' => '', 'version' => '0', 'plaintext' => false],
        $startup = false
    )
    {
        global $modx;

        if (empty($src)) {
            return;
        } // nothing to register

        if (!is_array($options)) {
            if (is_bool($options)) {
                $options = ['plaintext' => $options];
            } elseif (is_string($options)) {
                $options = ['name' => $options];
            } else {
                $options = [];
            }
        }
        $name = isset($options['name']) ? strtolower($options['name']) : '';
        $version = $options['version'] ?? '0';
        $plaintext = $options['plaintext'] ?? false;
        $key = $name ? $name : $src;

        $useThisVer = true;
        if (isset($modx->loadedjscripts[$key])) { // a matching script was found
            // if existing script is a startup script, make sure the candidate is also a startup script
            if ($modx->loadedjscripts[$key]['startup']) {
                $startup = true;
            }

            if (empty($name)) {
                $useThisVer = false; // if the match was based on identical source code, no need to replace the old one
            } else {
                $useThisVer = version_compare($modx->loadedjscripts[$key]['version'], $version, '<');
            }

            if ($useThisVer) {
                if ($startup == true && $modx->loadedjscripts[$key]['startup'] == false) {
                    // remove old script from the bottom of the page (new one will be at the top)
                    unset($modx->jscripts[$modx->loadedjscripts[$key]['pos']]);
                } else {
                    // overwrite the old script (the position may be important for dependent scripts)
                    $overwritepos = $modx->loadedjscripts[$key]['pos'];
                }
            } else {
                // Use the original version
                if ($startup == true && $modx->loadedjscripts[$key]['startup'] == false) {
                    // need to move the exisiting script to the head
                    $version = $modx->loadedjscripts[$key][$version];
                    $src = $modx->jscripts[$modx->loadedjscripts[$key]['pos']];
                    unset($modx->jscripts[$modx->loadedjscripts[$key]['pos']]);
                } else {
                    return;
                }
                // the script is already in the right place
            }
        }

        if ($useThisVer && $plaintext != true && (strpos(strtolower($src), "<script") === false)) {
            $src = "\t" . '<script type="text/javascript" src="' . $src . '"></script>';
        }

        if ($startup) {
            $pos = $overwritepos ?? max(array_merge([0], array_keys($modx->sjscripts))) + 1;
            $modx->sjscripts[$pos] = $src;
        } else {
            $pos = $overwritepos ?? max(array_merge([0], array_keys($modx->jscripts))) + 1;
            $modx->jscripts[$pos] = $src;
        }
        $modx->loadedjscripts[$key]['version'] = $version;
        $modx->loadedjscripts[$key]['startup'] = $startup;
        $modx->loadedjscripts[$key]['pos'] = $pos;
    }

    function regClientStartupHTMLBlock($html)
    { // Registers Client-side Startup HTML block
        $options = ['plaintext' => true];
        $startup = true;
        $this->regClientScript($html, $options, $startup);
    }

    function regClientHTMLBlock($html)
    { // Registers Client-side HTML block
        $options = ['plaintext' => true];
        $startup = false;
        $this->regClientScript($html, $options, $startup);
    }

    # Registers Startup Client-side JavaScript - these scripts are loaded at inside the <head> tag
    function regClientStartupScript($src, $options = ['name' => '', 'version' => '0', 'plaintext' => false])
    {
        $startup = true;
        $this->regClientScript($src, $options, $startup);
    }

    function checkPermissions($docid = false, $duplicateDoc = false)
    {
        if (strpos($docid, ',') !== false) {
            $docid = substr($docid, 0, strpos($docid, ','));
        }

        $allowroot = evo()->config('udperms_allowroot');

        if (evo()->hasPermission('save_role')) {
            return true;
        }

        if ($docid == 0 && $allowroot == 1) {
            return true;
        }

        if (!evo()->config('use_udperms')) {
            return true;
        }

        if ($docid === false) {
            return false;
        }

        $rs = db()->select('parent', '[+prefix+]site_content', "id='{$docid}'");
        $parent = db()->getValue($rs);
        if ($duplicateDoc == true && $parent == 0 && $allowroot == 0) {
            return false; // deny duplicate document at root if Allow Root is No
        }

        // get document groups for current user
        if (evo()->session('mgrDocgroups')) {
            foreach ($_SESSION['mgrDocgroups'] as $v) {
                $docgrp[] = "dg.document_group='{$v}'";
            }
            $docgrps = implode(' || ', $docgrp);
            $where_docgrp = "({$docgrps} || sc.privatemgr = 0)";
        } else {
            $where_docgrp = 'sc.privatemgr = 0';
        }

        $field = 'COUNT(DISTINCT sc.id)';
        $from = '[+prefix+]site_content sc';
        $from .= ' LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';
        $from .= ' LEFT JOIN [+prefix+]documentgroup_names dgn ON dgn.id = dg.document_group';
        $where = "sc.id='{$docid}' AND {$where_docgrp}";

        $rs = db()->select($field, $from, $where);
        $total = db()->count($rs);

        if ($total == 1) {
            return true;
        }
        return false;
    }

    /*
     * Template Variable Data Source @Bindings
     * Created by Raymond Irving Feb, 2005
     */

    function ProcessTVCommand($input, $name = '', $docid = '', $src = 'docform')
    {
        global $modx;
        $docid = (int)$docid ? (int)$docid : $modx->documentIdentifier;
        $input = trim($input);

        if (strpos($input, '@') === 0 && evo()->config('enable_bindings') != 1 && $src === 'docform') {
            return '@Bindings is disabled.';
        }

        [$CMD, $param] = $this->splitTVCommand($input);

        if (!$CMD) {
            return $input;
        }

        $CMD = '@' . trim($CMD);
        $param = trim($param);
        switch ($CMD) {
            case '@PARSE' :
            case '@MODX' :
                if (strpos($param, '[!') !== false) {
                    $param = str_replace(['[!', '!]'], ['[[', ']]'], $param);
                }
                if (strpos($param, '[*') !== false) {
                    $param = $modx->mergeDocumentContent($param);
                }
                if (strpos($param, '[(') !== false) {
                    $param = $modx->mergeSettingsContent($param);
                }
                if (strpos($param, '{{') !== false) {
                    $param = $modx->mergeChunkContent($param);
                }
                if (strpos($param, '[[') !== false) {
                    $param = $modx->evalSnippets($param);
                }
                $output = trim($param);
                break;
            case '@FILE' :
                if ($modx->getExtention($param) === '.php') {
                    $output = 'Could not retrieve PHP file.';
                } else {
                    $output = @file_get_contents($param);
                }
                if ($output === false) {
                    $output = " Could not retrieve document '{$param}'.";
                }
                break;
            case '@CHUNK' : // retrieve a chunk and process it's content
                $output = $modx->getChunk(trim($param));
                break;
            case '@DOCUMENT' : // retrieve a document and process it's content
            case '@DOC' :
                $rs = $modx->getDocument($param);
                if (is_array($rs)) {
                    $output = $rs['content'];
                } else {
                    $output = "Unable to locate document {$param}";
                }
                break;
            case '@SELECT' : // selects a record from the cms database
                $ph = [
                    'dbase' => db()->config['dbase'],
                    'DBASE' => db()->config['dbase'],
                    'prefix' => db()->config['table_prefix'],
                    'PREFIX' => db()->config['table_prefix']
                ];
                $param = $modx->parseText($param, $ph);
                $rs = db()->query("SELECT {$param}");
                if (db()->count($rs) == 0) {
                    return;
                }
                $output = $rs;
                break;
            case '@EVAL' : // evaluates text as php codes return the results
                $output = eval ($param);
                break;
            case '@INHERIT' :
                $output = $param;
                if (empty($docid) && isset($_REQUEST['pid'])) {
                    $doc['parent'] = $_REQUEST['pid'];
                } else {
                    $doc = $modx->getPageInfo($docid, 0, 'id,parent');
                }

                while ($doc['parent'] != 0) {
                    $doc = $modx->getPageInfo($doc['parent'], 0, 'id,parent');
                    $tv = $modx->getTemplateVar($name, '*', $doc['id'], null);
                    $value = (string)$tv['value'];
                    if ($value !== '' && strpos($value, '@INHERIT') !== 0) {
                        $output = $value;
                        break;
                    }
                }
                break;
            case '@DIRECTORY' :
            case '@DIR' :
                $files = [];
                $param = trim($param, '/');
                $path = MODX_BASE_PATH . $param;
                if (!is_dir($path)) {
                    exit($path);
                }

                $dir = dir($path);
                while (($file = $dir->read()) !== false) {
                    if (strpos($file, '.') !== 0) {
                        $files[] = "{$file}=={$param}{$file}";
                    }
                }
                asort($files);
                $output = implode('||', $files);
                break;
            case '@NULL' :
            case '@NONE' :
                $output = '';
                break;
            default :
                $output = $input;
                break;
        }
        // support for nested bindings
        if (is_string($output) && strpos($output, '@') === 0 && $output != $input) {
            $output = $this->ProcessTVCommand($output, $name, $docid, $src);
        }

        return $output;
    }

    // separate @ cmd from params
    function splitTVCommand($binding_string)
    {
        if (strpos($binding_string, '@') !== 0) {
            return [null, null];
        }
        if (strpos($binding_string, '@INHERIT') === 0) {
            return ['INHERIT', ''];
        }

        if (strpos($binding_string, '@@EVAL') === 0) {
            $binding_string = substr($binding_string, 1);
        }

        $BINDINGS = explode(',', 'PARSE,MODX,FILE,CHUNK,DOCUMENT,DOC,SELECT,EVAL,INHERIT,DIRECTORY,DIR,NULL,NONE');
        $binding_array = [];
        foreach ($BINDINGS as $CMD) {
            if (strpos($binding_string, "@{$CMD}") !== 0) {
                continue;
            }
            $code = substr($binding_string, strlen($CMD) + 2);
            $binding_array = [$CMD, trim($code)];
            break;
        }
        return $binding_array ?: [null, null];
    }

    function getExtention($str)
    {
        $str = trim($str);
        $str = strtolower($str);
        $pos = strrpos($str, '.');
        if ($pos === false) {
            return false;
        }
        return substr($str, $pos);
    }

    function decodeParamValue($s)
    {
        $s = str_replace(
            ['%3B', '%3D', '%26', '%2C', '%5C'],
            [';', '=', '&', ',', '\\'],
            $s
        );
        return $s;
    }

    // returns an array if a delimiter is present. returns array is a recordset is present
    function parseInput($src, $delim = '||', $type = 'string', $columns = true)
    { // type can be: string, array
        if (db()->isResult($src)) {
            // must be a recordset
            $rows = [];
            db()->numFields($src);
            while ($cols = db()->getRow($src, 'num')) {
                $rows[] = ($columns) ? $cols : implode(' ', $cols);
            }
            return ($type == 'array') ? $rows : implode($delim, $rows);
        }

        if ($type === 'array') {
            return explode($delim, $src);
        }
        return $src;
    }

    function getUnixtimeFromDateString($value)
    {
        $timestamp = false;
        // Check for MySQL or legacy style date
        $date_match_1 = '/^([0-9]{2})-([0-9]{2})-([0-9]{4}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$/';
        $date_match_2 = '/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$/';
        $matches = [];
        if (strpos($value, '-') !== false) {
            if (preg_match($date_match_1, $value, $matches)) {
                $timestamp = mktime(
                    $matches[4],
                    $matches[5],
                    $matches[6],
                    $matches[2],
                    $matches[1],
                    $matches[3]
                );
            } elseif (preg_match($date_match_2, $value, $matches)) {
                $timestamp = mktime(
                    $matches[4],
                    $matches[5],
                    $matches[6],
                    $matches[2],
                    $matches[3],
                    $matches[1]
                );
            }
        }
        // If those didn't work, use strtotime to figure out the date
        if ($timestamp === false || $timestamp === -1) {
            $timestamp = strtotime($value);
        }
        return $timestamp;
    }

    // DISPLAY FORM ELEMENTS
    public function renderFormElement(
        $field_type,
        $field_id,
        $default_text = '',
        $field_elements = '',
        $field_value = '',
        $field_style = '',
        $row = []
    )
    {
        global $modx, $content;

        $field_type = strtolower($field_type);

        if (isset($content['id'])) {
            global $docObject;
            if ($docObject) {
                $modx->documentObject = $docObject;
            } elseif (!isset($modx->documentObject)) {
                $modx->documentObject = $modx->getDocumentObject('id', $content['id']);
            }

            if (!isset($modx->documentIdentifier)) {
                $modx->documentIdentifier = $content['id'];
            }
        }

        if (strpos($default_text, '<?php') === 0) {
            $default_text = "@@EVAL:\n" . substr($default_text, 6);
        }
        if (strpos($field_value, '<?php') === 0) {
            $field_value = "@@EVAL:\n" . substr($field_value, 6);
        }

        if (strpos($default_text, '@@EVAL') === 0 && $field_value === $default_text) {
            $default_text = eval(trim(substr($default_text, 7)));
            $field_value = $default_text;
        }

        if (in_array($field_type, ['text', 'rawtext', 'email', 'number', 'zipcode', 'tel', 'url'])) {
            return $this->rendarFormText($field_type, $field_id, $field_value, $field_style);
        }

        if (in_array($field_type, ['textarea', 'rawtextarea', 'htmlarea', 'richtext', 'textareamini'])) {
            return $this->rendarFormTextarea($field_type, $field_id, $field_value, $field_style);
        }
        if (in_array($field_type, ['date', 'dateonly'])) {
            return $this->rendarFormDate($field_type, $field_id, $field_value, $field_style);
        }

        if ($field_type === 'image') {
            return $this->rendarFormImage($field_id, $field_value, $field_style);
        }
        if ($field_type === 'file') {
            return $this->rendarFormFile($field_id, $field_value, $field_style);
        }
        if ($field_type === 'hidden') {
            return $this->rendarFormHidden($field_id, $field_value);
        }

        if (strpos($field_elements, '<?php') === 0) {
            $field_elements = "@EVAL:\n" . substr($field_elements, 6);
        }
        if (strpos($field_elements, '@@EVAL') === 0) {
            $field_elements = "@EVAL:\n" . substr($field_elements, 7);
        }

        if (in_array($field_type, ['dropdown', 'listbox', 'listbox-multiple'])) {
            return $this->rendarFormSelect($field_type, $field_id, $field_value, $field_elements);
        }
        if ($field_type === 'checkbox') {
            return $this->rendarFormCheckbox($field_type, $field_id, $field_value, $field_elements);
        }
        if ($field_type === 'option') {
            return $this->rendarFormRadio($field_id, $field_value, $field_elements);
        }
        if ($field_type === 'custom_tv') {
            return $this->rendarFormCustom(
                $field_type,
                $field_id,
                $field_value,
                $field_style,
                $field_elements,
                $default_text
            );
        }

        // the default handler -- for errors, mostly
        if (strpos($field_elements, '@EVAL') === 0) {
            return eval(trim(substr($field_elements, 6)));
        }

        $result = db()->select(
            'snippet',
            '[+prefix+]site_snippets',
            sprintf("name='input:%s'", $field_type)
        );
        if (db()->count($result) == 1) {
            return eval(db()->getValue($result));
        }

        return sprintf(
            '<input type="text" id="tv%s" name="tv%s" value="%s" %s />',
            $field_id,
            $field_id,
            $modx->hsc($field_value),
            $field_style
        );
    }

    private function rendarFormText($field_type, $field_id, $field_value, $field_style)
    {
        if ($field_type === 'text') {
            $class = 'text';
        } elseif ($field_type === 'number') {
            $class = 'text imeoff';
        } else {
            $class = 'text ' . $field_type;
        }
        return evo()->parseText(
            file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_text.tpl'),
            [
                'class'  => $class,
                'id'     => 'tv' . $field_id,
                'name'   => 'tv' . $field_id,
                'value'  => evo()->hsc($field_value),
                'style'  => $field_style,
                'tvtype' => $field_type
            ]
        );
    }

    private function rendarFormTextarea($field_type, $field_id, $field_value, $field_style)
    {
        return evo()->parseText(
            file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_textarea.tpl'),
            [
                'id'     => 'tv' . $field_id,
                'name'   => 'tv' . $field_id,
                'value'  => evo()->hsc($field_value),
                'style'  => $field_style,
                'tvtype' => $field_type,
                'rows'   => $field_type === 'textareamini' ? '5' : '15'
            ]
        );
    }

    private function rendarFormDate($field_type, $field_id, $field_value, $field_style)
    {
        $format = evo()->config('datetime_format');
        if ($field_type === 'date') {
            $format .= ' hh:mm:00';
        }
        return evo()->parseText(
            file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_date.tpl'),
            [
                'id' => sprintf(
                    'tv%s',
                    str_replace(['-', '.'], '_', urldecode($field_id))
                ),
                'name'            => 'tv' . $field_id,
                'value'           => $field_value ? evo()->hsc($field_value) : '',
                'style'           => $field_style,
                'tvtype'          => $field_type,
                'cal_nodate'      => style('icons_cal_nodate'),
                'yearOffset'      => evo()->config('datepicker_offset'),
                'datetime_format' => $format,
                'timepicker'      => $field_type === 'date' ? 'true' : 'false'
            ]
        );
    }

    private function rendarFormSelect($field_type, $field_id, $field_value, $field_elements)
    {
        $tpl = file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_list.tpl');
        if ($field_type === 'listbox-multiple' && strpos($tpl, '[+name+][]') === false) {
            $tpl = str_replace('[+name+]', '[+name+][]', $tpl);
        }
        $index_list = $this->ParseInputOptions(
            $this->ProcessTVCommand($field_elements, $field_id, '', 'tvform')
        );
        $field_values = explode('||', $field_value);
        $options = [];
        foreach ($index_list as $item) {
            [$label, $value] = $this->splitOption($item);
            $options[] = evo()->parseText(
                '<option value="[+value+]" [+selected+]>[+label+]</option>',
                [
                    'label' => $label,
                    'value' => evo()->hsc($value),
                    'selected' => in_array($value, $field_values) ? 'selected="selected"' : ''
                ]
            );
        }
        if ($field_type === 'dropdown') {
            $size = '1';
        } else {
            $size = count($index_list) < 8 ? count($index_list) : 8;
        }
        return evo()->parseText(
            $tpl,
            [
                'options' => implode("\n", $options),
                'id' => 'tv' . $field_id,
                'name' => 'tv' . $field_id,
                'size' => $size,
                'extra' => ($field_type === 'listbox-multiple') ? 'multiple' : ''
            ]
        );
    }

    private function rendarFormCheckbox($field_type, $field_id, $field_value, $field_elements)
    {
        if (!is_array($field_value)) {
            $field_value = explode('||', $field_value);
        }
        $index_list = $this->ParseInputOptions(
            $this->ProcessTVCommand($field_elements, $field_id, '', 'tvform')
        );
        $tpl = file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_checkbox.tpl');
        $field_html = '';
        $i = 0;
        foreach ($index_list as $item) {
            [$label, $value] = $this->splitOption($item);
            $field_html .= evo()->parseText(
                $tpl,
                [
                    'id' => 'tv' . $field_id . '_' . $i,
                    'name' => 'tv' . $field_id . '[]',
                    'value' => evo()->hsc($value),
                    'tvtype' => $field_type,
                    'label' => $label,
                    'checked' => $this->isSelected($label, $value, $item, $field_value) ?
                        ' checked' : ''
                ]
            );
            $i++;
        }
        return trim($field_html);
    }

    private function rendarFormRadio($field_id, $field_value, $field_elements)
    {
        $index_list = $this->ParseInputOptions(
            $this->ProcessTVCommand($field_elements, $field_id, '', 'tvform')
        );
        $i = 0;
        $field_html = '';
        $tpl = file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_radio.tpl');
        foreach ($index_list as $item) {
            [$label, $value] = $this->splitOption($item);
            $field_html .= evo()->parseText(
                $tpl,
                [
                    'i'       => $i,
                    'value'   => evo()->hsc($value),
                    'id'      => $field_id,
                    'checked' => $this->isSelected($label, $value, $item, $field_value)
                        ? 'checked="checked"' : '',
                    'label' => $label
                ]
            );
            $i++;
        }
        return $field_html;
    }

    private function rendarFormImage($field_id, $field_value, $field_style)
    {
        return sprintf(
            file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_image.tpl'),
            $field_id,
            $field_id,
            $field_value,
            $field_style,
            lang('insert'),
            $field_id
        );
    }

    private function rendarFormFile($field_id, $field_value, $field_style)
    {
        return sprintf(
            file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_file.tpl'),
            $field_id,
            $field_id,
            $field_value,
            $field_style,
            lang('insert'),
            $field_id
        );
    }

    private function rendarFormHidden($field_id, $field_value)
    {
        return sprintf(
            '<input type="hidden" id="tv%s" name="tv%s" value="%s" tvtype="%s" />',
            $field_id,
            $field_id,
            evo()->hsc($field_value),
            'hidden'
        );
    }

    private function rendarFormCustom($field_type, $field_id, $field_value, $field_style, $field_elements, $default_text)
    {
        $ph['field_type'] = $field_type;
        $ph['field_id'] = $field_id;
        $ph['field_name'] = "tv" . $field_id;
        $ph['name'] = "tv" . $field_id;
        $ph['default_text'] = $default_text;
        $ph['field_value'] = evo()->hsc($field_value);
        $ph['value'] = evo()->hsc($field_value);
        $ph['field_style'] = $field_style;
        return evo()->evalSnippets(
            evo()->mergeChunkContent(
                evo()->mergeSettingsContent(
                    evo()->mergeDocumentContent(
                        evo()->parseText(
                            $this->custom_tv_tpl($field_id, $field_elements, $ph),
                            $ph
                        )
                    )
                )
            )
        );
    }

    private function custom_tv_tpl($field_id, $field_elements, $ph)
    {
        global $modx, $_lang;
        if (strpos($field_elements, '@FILE') === 0) {
            $path_str = trim(substr($field_elements, 6));
            $lfpos = strpos($path_str, "\n");
            if ($lfpos !== false) {
                $path_str = substr($path_str, 0, $lfpos);
            }
            $path_str = MODX_BASE_PATH . trim($path_str);

            if (!is_file($path_str)) {
                return $path_str . ' does not exist';
            }

            return file_get_contents($path_str);
        }

        if (strpos($field_elements, '@INCLUDE') === 0) {
            $path_str = substr($field_elements, 9);
            if (strpos($path_str, "\n") !== false) {
                $path_str = strstr($path_str, "\n", true);
            }
            $path_str = trim($path_str);
            if (is_file(MODX_BASE_PATH . 'assets/tvs/' . $path_str)) {
                $path = MODX_BASE_PATH . 'assets/tvs/' . $path_str;
            } elseif (is_file(MODX_BASE_PATH . ltrim($path_str, '/'))) {
                $path = MODX_BASE_PATH . ltrim($path_str, '/');
            } else {
                return $path_str . ' does not exist';
            }
            extract($ph);
            ob_start();
            $return = include $path;
            return ob_get_clean() ?: $return;
        }

        if (strpos($field_elements, '@CHUNK') === 0) {
            $chunk_name = trim(substr($field_elements, 7));
            $tpl = $modx->getChunk($chunk_name);
            if ($tpl !== false) {
                return $tpl;
            }
            return sprintf(
                '%s(%s:%s)',
                $_lang['chunk_no_exist'],
                $_lang['htmlsnippet_name'],
                $chunk_name
            );
        }

        if (strpos($field_elements, '@EVAL') === 0) {
            extract($ph);
            return eval(
                trim(substr($field_elements, 6))
            );
        }

        if (strpos($field_elements, '@') === 0) {
            return $this->ProcessTVCommand(
                $field_elements,
                $field_id,
                '',
                'tvform'
            );
        }
        return $field_elements;
    }

    function ParseInputOptions($v)
    {
        if (is_array($v)) {
            return $v;
        }

        if (db()->isResult($v)) {
            $a = [];
            while ($cols = db()->getRow($v, 'num')) {
                $a[] = $cols;
            }
            return $a;
        }

        $v = trim($v);
        if (strpos($v, '||') !== false) {
            return explode('||', $v);
        }

        if (strpos($v, "\n") !== false) {
            $v = str_replace("\n", '||', $v);
        } elseif (strpos($v, ',') !== false) {
            $v = str_replace(',', '||', $v);
        }

        return explode('||', $v);
    }

    function splitOption($value)
    {
        if (is_array($value)) {
            $label = $value[0];
            $value = isset($value[1]) ? $value[1] : $value[0];
        } else {
            if (strpos($value, '==') === false) {
                $label = $value;
            } else {
                [$label, $value] = explode('==', $value, 2);
            }
        }
        return [trim($label), trim($value)];
    }

    function isSelected($label, $value, $item, $field_value)
    {
        if (is_array($item)) {
            $item = $item['0'];
        }

        if (strpos($item, '==') !== false && strlen($value) == 0) {
            if (is_array($field_value)) {
                $rs = in_array($label, $field_value);
            } else {
                $rs = ($label === $field_value);
            }
        } else {
            if (is_array($field_value)) {
                $rs = in_array($value, $field_value);
            } else {
                $rs = ($value === $field_value);
            }
        }
        return $rs;
    }

    /**
     * Displays a javascript alert message in the web browser and quit
     *
     * @param string $msg Message to show
     * @param string $url URL to redirect to
     */
    function webAlertAndQuit($msg, $url = "")
    {
        global $modx, $modx_manager_charset;
        if (strpos(strtolower($url), 'javascript:') === 0) {
            $fnc = substr($url, 11);
        } elseif ($url) {
            $fnc = "window.location.href='" . addslashes($url) . "';";
        } elseif (isset($_SESSION['previous_request_uri'])) {
            $fnc = sprintf("window.location.href='%s';", $_SESSION['previous_request_uri']);
        } else {
            $fnc = "history.back(-1);";
        }
        $msg = addslashes($msg);
        $msg = str_replace("\n", '\n', $msg);
        echo "<html><head>
            <title>MODX :: Alert</title>
            <meta http-equiv=\"Content-Type\" content=\"text/html; charset={$modx_manager_charset};\">
            <link rel=\"stylesheet\" type=\"text/css\" href=\"media/style/{$modx->config['manager_theme']}/style.css\" />
            <script>
                function __alertQuit() {
                    alert('" . $msg . "');
                    {$fnc}
                }
                window.setTimeout('__alertQuit();',100);
            </script>
            </head><body>
            </body></html>";
        exit;
    }

    function getMimeType($filepath = '')
    {
        $fp = fopen($filepath, 'rb');
        $head = fread($fp, 2);
        fclose($fp);
        $head = mb_convert_encoding($head, '8BIT');

        if ($head === 'BM') {
            return 'image/bmp';
        }

        if ($head === 'GI') {
            return 'image/gif';
        }

        if ($head === chr(0xFF) . chr(0xd8)) {
            return 'image/jpeg';
        }

        if ($head === chr(0x89) . 'P') {
            return 'image/png';
        }

        return false;
    }

    # returns true if the current web user is a member the specified groups
    function isMemberOfWebGroup($groupNames = [])
    {
        if (!is_array($groupNames)) {
            return false;
        }

        // check cache
        $grpNames = isset ($_SESSION['webUserGroupNames']) ? $_SESSION['webUserGroupNames'] : false;
        if (!is_array($grpNames)) {
            $uid = evo()->getLoginUserID();
            $from = '[+prefix+]webgroup_names wgn' .
                " INNER JOIN [+prefix+]web_groups wg ON wg.webgroup=wgn.id AND wg.webuser='{$uid}'";
            $rs = db()->select('wgn.name', $from);
            $grpNames = db()->getColumn('name', $rs);

            // save to cache
            $_SESSION['webUserGroupNames'] = $grpNames;
        }
        foreach ($groupNames as $k => $v) {
            if (in_array(trim($v), $grpNames, true)) {
                return true;
            }
        }
        return false;
    }

    # Returns a record for the web user
    function getWebUserInfo($uid)
    {
        $field = 'wu.username, wu.password, wua.*';
        $from = '[+prefix+]web_users wu INNER JOIN [+prefix+]web_user_attributes wua ON wua.internalkey=wu.id';
        $rs = db()->select($field, $from, "wu.id='$uid'");
        $limit = db()->count($rs);
        if ($limit == 1) {
            $row = db()->getRow($rs);
            if (!$row['usertype']) {
                $row['usertype'] = 'web';
            }
            return $row;
        }
        return false;
    }

    # Returns a record for the manager user
    function getUserInfo($uid)
    {
        $rs = db()->select(
            'user.username, user.password, attrib.*',
            [
                '[+prefix+]manager_users user',
                'INNER JOIN [+prefix+]user_attributes attrib ON attrib.internalKey=user.id'
            ],
            sprintf("user.id='%s'", db()->escape($uid))
        );
        if (db()->count($rs) == 1) {
            $row = db()->getRow($rs);
            if (!isset($row['usertype'])) {
                $row['usertype'] = 'manager';
            }
            if (!isset($row['failedlogins'])) {
                $row['failedlogins'] = 0;
            }
            return $row;
        }
        return false;
    }

    # Returns current user name
    function getLoginUserName($context = '')
    {
        if ($context && evo()->session($context . 'Validated')) {
            return evo()->session($context . 'Shortname');
        }

        if (evo()->isFrontend() && evo()->session('webValidated')) {
            return evo()->session('webShortname');
        }

        if (evo()->isBackend() && evo()->session('mgrValidated')) {
            return evo()->session('mgrShortname');
        }

        return false;
    }

    # Returns current login user type - web or manager
    function getLoginUserType()
    {
        if (evo()->isFrontend() && evo()->session('webValidated')) {
            return 'web';
        }

        if (evo()->isBackend() && evo()->session('mgrValidated')) {
            return 'manager';
        }

        return '';
    }

    function getDocumentChildrenTVars(
        $parentid   = 0,
        $tvidnames  = '*',
        $published  = 1,
        $docsort    = 'menuindex',
        $docsortdir = 'ASC',
        $tvfields   = '*',
        $tvsort     = 'rank',
        $tvsortdir  = 'ASC'
    )
    {
        global $modx;

        $docs = $modx->getDocumentChildren(
            $parentid,
            $published,
            0,
            '*',
            '',
            $docsort,
            $docsortdir
        );
        if (!$docs) {
            return false;
        }

        foreach ($docs as $doc) {
            $result[] = $modx->getTemplateVars(
                $tvidnames,
                $tvfields,
                $doc['id'],
                $published
            );
        }
        return $result;
    }

    function getDocumentChildrenTVarOutput(
        $parentid = 0,
        $tvidnames = '*',
        $published = 1,
        $docsort = 'menuindex',
        $docsortdir = 'ASC'
    )
    {
        global $modx;

        $docs = $modx->getDocumentChildren($parentid, $published, 0, '*', '', $docsort, $docsortdir);
        if (!$docs) {
            return false;
        }

        $result = [];
        foreach ($docs as $doc) {
            $tvs = $modx->getTemplateVarOutput($tvidnames, $doc['id'], $published, '', '');
            if ($tvs) {
                $result[$doc['id']] = $tvs;
            } // Use docid as key - netnoise 2006/08/14
        }
        return $result;
    }

    function getAllChildren(
        $id = 0,
        $sort = 'menuindex',
        $dir = 'ASC',
        $fields = 'id, pagetitle, description, parent, alias, menutitle',
        $where = false
    )
    {
        global $modx;
        static $cache = [];

        $cacheKey = hash('crc32b', print_r(func_get_args(), true));
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        // modify field names to use sc. table reference
        $fields = $modx->join(',', explode(',', $fields), 'sc.');
        $sort = $modx->join(',', explode(',', $sort), 'sc.');

        // build query
        $from = '[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';
        if ($where === false) {
            // get document groups for current user
            if ($modx->getUserDocGroups()) {
                $docgrp = implode(',', $modx->getUserDocGroups());
                $cond = sprintf(
                    "OR dg.document_group IN (%s) OR 1='%s'",
                    $docgrp,
                    $_SESSION['mgrRole']
                );
            } else {
                $cond = '';
            }
            $context = ($modx->isFrontend() ? 'web' : 'mgr');
            $where = sprintf(
                "sc.parent = '%s' AND (sc.private%s=0 %s) GROUP BY sc.id",
                $id,
                $context,
                $cond
            );
        }
        $orderby = "{$sort} {$dir}";
        $result = db()->select("DISTINCT {$fields}", $from, $where, $orderby);
        $resourceArray = [];
        while ($row = db()->getRow($result)) {
            $resourceArray[] = $row;
        }

        $cache[$cacheKey] = $resourceArray;

        return $resourceArray;
    }

    function getActiveChildren(
        $id = 0,
        $sort = 'menuindex',
        $dir = 'ASC',
        $fields = 'id, pagetitle, description, parent, alias, menutitle'
    )
    {
        global $modx;
        static $cache = [];

        $cacheKey = hash('crc32b', print_r(func_get_args(), true));
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }
        $where = [
            sprintf("sc.parent = '%s'", $id),
            'AND sc.published=1',
            'AND sc.deleted=0'
        ];
        if ($modx->isFrontend()) {
            if ($modx->getUserDocGroups()) {
                $where[] = sprintf(
                    "AND (sc.privateweb=0 OR dg.document_group IN (%s))",
                    implode(',', $modx->getUserDocGroups())
                );
            } else {
                $where[] = 'AND sc.privateweb=0';
            }
        } elseif ($_SESSION['mgrRole'] != 1) {
            if ($modx->getUserDocGroups()) {
                $where[] = sprintf(
                    "AND (sc.privatemgr=0 OR dg.document_group IN (%s))",
                    implode(',', $modx->getUserDocGroups())
                );
            } else {
                $where[] = 'AND sc.privatemgr=0';
            }
        }
        $where[] = "GROUP BY sc.id";

        $resourceArray = $modx->getAllChildren($id, $sort, $dir, $fields, $where);

        $cache[$cacheKey] = $resourceArray;

        return $resourceArray;
    }

    function getDocumentChildren(
        $parentid = 0,
        $published = 1,
        $deleted = 0,
        $fields = '*',
        $customWhere = '',
        $sort = 'menuindex',
        $dir = 'ASC',
        $limit = ''
    )
    {
        global $modx;

        // modify field names to use sc. table reference
        $fields = $modx->join(',', explode(',', $fields), 'sc.');

        $from = '[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';

        $access = '';
        if ($modx->isFrontend()) {
            $access = 'sc.privateweb=0';
        } elseif ($_SESSION['mgrRole'] != 1) {
            $access = 'sc.privatemgr=0';
        }
        if ($docgrp = $modx->getUserDocGroups()) {
            if ($access !== '') {
                $access .= ' OR';
            }
            $access .= sprintf(' dg.document_group IN (%s)', implode(',', $docgrp));
        }

        $_ = [];
        $_[] = "sc.parent='{$parentid}'";
        $_[] = "sc.published={$published}";
        $_[] = "sc.deleted={$deleted}";
        if ($customWhere != '') {
            $_[] = $customWhere;
        }
        if ($access != '') {
            $_[] = "({$access})";
        }
        $where = implode(' AND ', $_) . ' GROUP BY sc.id';

        if (strpos($sort, ',') !== false) {
            $orderby = $modx->join(',', explode(',', $sort), 'sc.');
        } else {
            $orderby = "{$sort} {$dir}";
        }

        $result = db()->select("DISTINCT {$fields}", $from, $where, $orderby, $limit);
        $resourceArray = [];
        while ($row = db()->getRow($result)) {
            $resourceArray[] = $row;
        }
        return $resourceArray;
    }

    function getPreviewObject($input = [])
    {
        global $modx;

        try {
            if ($modx->previewObject) {
                return $modx->previewObject;
            }

            if (!isset($input['id']) || empty($input['id'])) {
                $input['id'] = evo()->config('site_start');
            }

            $modx->documentIdentifier = $input['id'];

            $rs = db()->select(
                'id,name,type,display,display_params',
                '[+prefix+]site_tmplvars'
            );
            $tvname = [];
            while ($row = db()->getRow($rs)) {
                $tvid = 'tv' . $row['id'];
                $tvname[$tvid] = $row['name'];
            }

        foreach ($input as $k => $v) {
            if (isset($tvname[$k])) {
                if (is_array($v)) {
                    $v = implode('||', $v);
                }
                $name = $tvname[$k];
                $prefix_key = "{$k}_prefix";
                if (isset($input[$prefix_key])) {
                    if ($input[$prefix_key] !== 'DocID') {
                        $v = $input[$prefix_key] . $v;
                    } elseif (preg_match('/\A[0-9]+\z/', $v)) {
                        $v = '[~' . $v . '~]';
                    }
                }
                unset($input[$k]);
                $input[$name] = $v;
                continue;
            }
            if ($k === 'ta') {
                $input['content'] = $v;
                unset($input['ta']);
            }
        }

        $input['pub_date']    = evo()->toTimeStamp($input['pub_date'] ?? 0);
        $input['unpub_date']  = evo()->toTimeStamp($input['unpub_date'] ?? 0);
        $input['publishedon'] = evo()->toTimeStamp($input['publishedon'] ?? 0);

        $modx->previewObject = $input;

            return $modx->previewObject;

        } catch (Throwable $e) {
            $modx->logEvent(
                0,
                3,
                "getPreviewObject: Error occurred - " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine(),
                'getPreviewObject - Error'
            );
            return [];
        }
    }

    function loadLexicon($target = 'manager')
    {
        $langname = evo()->config('manager_language', 'english');

        if ($target === 'manager') {
            global $_lang, $modx_manager_charset, $modx_lang_attribute, $modx_textdir;
            $path = MODX_CORE_PATH . 'lang/';
            $modx_manager_charset = 'utf-8';
            $modx_lang_attribute = 'en';
            $modx_textdir = 'ltr';
            $_lang = [];
        } elseif ($target === 'locale') {
            global $_lc;
            $path = MODX_CORE_PATH . 'lang/locale/';
        } else {
            $path = $target;
        }

        $path = rtrim($path, '/') . '/';

        $file_path = "{$path}{$langname}.inc.php";
        if (is_file($file_path)) {
            include_once($file_path);
        }
    }

    function snapshot($filename = '', $target = '')
    {
        global $modx, $settings_version;

        if (is_array($filename)) {
            if (!isset($filename['filename'])) {
                $filename = '';
            } else {
                $filename = $filename['filename'];
            }
            if (!isset($filename['target'])) {
                $target = '';
            } else {
                $target = $filename['target'];
            }
        }

        if (strpos($filename, '/') !== false) {
            return;
        }
        if (strpos($filename, '\\') !== false) {
            return;
        }
        if ($target !== '') {
            $target = substr(strtolower($target), 0, 1);
        }

        if (!evo()->config('snapshot_path')) {
            if (is_dir(MODX_BASE_PATH . 'temp/backup')) {
                $snapshot_path = MODX_BASE_PATH . 'temp/backup/';
            } elseif (is_dir(MODX_BASE_PATH . 'assets/backup')) {
                $snapshot_path = MODX_BASE_PATH . 'assets/backup/';
            }
        } else {
            $snapshot_path = evo()->config('snapshot_path');
        }

        if ($filename === '') {
            $today = $modx->toDateFormat(request_time());
            $today = str_replace(
                ['/', ' ', ':'],
                ['-', '-', ''],
                $today
            );
            $today = strtolower($today);
            $filename = "{$today}-{$settings_version}.sql";
        }

        include_once(MODX_CORE_PATH . 'mysql_dumper.class.inc.php');
        $dumper = new Mysqldumper();
        $dumper->mode = 'snapshot';
        if ($target === 'c') {
            $dumper->contentsOnly = true;
        }
        $output = $dumper->createDump();
        return $dumper->snapshot($snapshot_path . $filename, $output);
    }

    /**
     * Returns the MODX version information as version, branch, release date and full application name.
     *
     * @return array
     */

    function getVersionData($data = null)
    {
        global $modx;
        if (!$modx->version || !is_array($modx->version)) {
            //include for compatibility modx version < 1.0.10
            include MODX_CORE_PATH . 'version.inc.php';
            $modx->version = [];
            $modx->version['version'] = $modx_version ?? '';
            $modx->version['branch'] = $modx_branch ?? '';
            $modx->version['release_date'] = $modx_release_date ?? '';
            $modx->version['full_appname'] = $modx_full_appname ?? '';
            $modx->version['new_version'] = evo()->config('newversiontext', '');
        }
        return ($data !== null && is_array($modx->version) && isset($modx->version[$data])) ? $modx->version[$data] : $modx->version;
    }

    function genTokenString($seed = '')
    {
        static $tokenString = null;
        if ($tokenString) {
            return $tokenString;
        }
        if (!$seed) {
            $seed = md5(mt_rand());
        }
        $_ = str_split($seed, 5);
        $p = [];
        foreach ($_ as $v) {
            $p[] = base_convert($v, 16, 36);
        }
        $tokenString = substr(
            implode('', $p),
            0,
            12
        );
        return $tokenString;
    }

    function setCacheRefreshTime($unixtime = 0)
    {
        if ($unixtime == 0) {
            return;
        }
        if (db()->isConnected() || !db()->tableExists('[+prefix+]system_settings')) {
            return;
        }
        include_once MODX_CORE_PATH . 'cache_sync.class.php';
        $cache = new synccache();
        $cache->setCacheRefreshTime($unixtime);
        $cache->publishBasicConfig();
    }

    function atBind($str = '')
    {
        if (strpos($str, '@') !== 0) {
            return $str;
        }

        if (strpos($str, '@FILE') === 0) {
            return $this->atBindFile($str);
        }
        if (strpos($str, '@URL') === 0) {
            return $this->atBindUrl($str);
        }
        if (strpos($str, '@INCLUDE') === 0) {
            return $this->atBindInclude($str);
        }

        return $str;
    }

    function atBindFile($str = '')
    {
        if (strpos($str, '@FILE') !== 0) {
            return $str;
        }
        $str = trim($str);
        if (strpos($str, "\n") !== false) {
            $str = substr($str, 0, strpos("\n", $str));
        }

        $str = substr($str, 6);
        $str = trim($str);
        $str = str_replace('\\', '/', $str);
        $template_path = 'assets/templates/';

        if (strpos($str, '/') === 0) {
            if (is_file($str) && strpos($str, MODX_MANAGER_PATH) === 0) {
                $file_path = false;
            } elseif (is_file($str) && strpos($str, MODX_BASE_PATH) === 0) {
                $file_path = $str;
            } elseif (is_file(MODX_BASE_PATH . trim($str, '/'))) {
                $file_path = MODX_BASE_PATH . trim($str, '/');
            } else {
                $file_path = false;
            }
        } elseif (is_file(MODX_BASE_PATH . $str)) {
            $file_path = MODX_BASE_PATH . $str;
        } elseif (is_file(MODX_BASE_PATH . $template_path . $str)) {
            $file_path = MODX_BASE_PATH . $template_path . $str;
        } else {
            $file_path = false;
        }

        if (!$file_path) {
            return false;
        }

        if (evo()->getExtention($file_path) === '.php') {
            return 'Could not retrieve PHP file.';
        }

        $content = file_get_contents($file_path);
        if (!$content) {
            return '';
        }

        global $recent_update;
        if ($recent_update < filemtime($file_path)) {
            evo()->clearCache();
        }
        if (!evo()->template_path && strpos($file_path, MODX_BASE_PATH . 'assets/templates/') === 0) {
            evo()->template_path = $file_path . '/';
        }

        return $content;
    }

    function atBindUrl($str = '')
    {
        if (strpos($str, '@URL') !== 0) {
            return $str;
        }

        $str = trim($str);
        $pos = strpos($str, "\n");
        if ($pos) {
            $str = substr($str, 0, $pos);
        }

        $str = substr($str, 5);
        $str = trim($str);
        if (strpos($str, 'http') !== 0) {
            return 'Error @URL';
        }

        return file_get_contents($str);
    }

    function atBindInclude($str = '')
    {
        if (strpos($str, '@INCLUDE') !== 0) {
            return $str;
        }
        $str = trim($str);
        if (strpos($str, "\n") !== false) {
            $str = substr($str, 0, strpos("\n", $str));
        }

        $str = substr($str, 9);
        $str = trim($str);
        $str = str_replace('\\', '/', $str);

        $tpl_dir = 'assets/templates/';

        if (strpos($str, '/') === 0) {
            $vpath = MODX_BASE_PATH . ltrim($str, '/');
            if (is_file($str) && strpos($str, MODX_MANAGER_PATH) === 0) {
                $file_path = false;
            } elseif (is_file($vpath) && strpos($vpath, MODX_MANAGER_PATH) === 0) {
                $file_path = false;
            } elseif (is_file($str) && strpos($str, MODX_BASE_PATH) === 0) {
                $file_path = $str;
            } elseif (is_file($vpath)) {
                $file_path = $vpath;
            } else {
                $file_path = false;
            }
        } elseif (is_file(MODX_BASE_PATH . $str)) {
            $file_path = MODX_BASE_PATH . $str;
        } elseif (is_file(MODX_BASE_PATH . "{$tpl_dir}{$str}")) {
            $file_path = MODX_BASE_PATH . $tpl_dir . $str;
        } else {
            $file_path = false;
        }
        if (!$file_path || !is_file($file_path)) {
            return false;
        }

        ob_start();
        $result = include($file_path);
        if ($result === 1) {
            $result = '';
        }
        $content = ob_get_clean();
        if (!$content && $result) {
            $content = $result;
        }
        return $content;
    }

    function setOption($key, $value = '')
    {
        global $modx;

        $modx->config[$key] = $value;
    }

    function getOption($key, $default = null, $options = null, $skipEmpty = false)
    {
        global $modx;

        $option = $default;

        if (strpos($key, ',') !== false) {
            $key = explode(',', $key);
        }
        if (is_array($key)) {
            if (!is_array($option)) {
                $default = $option;
                $option = [];
            }
            foreach ($key as $k) {
                $k = trim($k);
                $option[$k] = $this->getOption($k, $default, $options);
            }
            return $option;
        }

        if (is_string($key) && $key) {
            if (is_array($options) && array_key_exists($key,
                    $options) && (!$skipEmpty || ($skipEmpty && $options[$key] !== ''))) {
                return $options[$key];
            }
            if (is_array($modx->config) && array_key_exists($key,
                    $modx->config) && (!$skipEmpty || ($skipEmpty && $modx->config[$key] !== ''))) {
                return $modx->config[$key];
            }
        }
        return $option;
    }

    function regOption($key, $value = '')
    {
        global $modx;

        $modx->config[$key] = $value;
        $f['setting_name'] = $key;
        $f['setting_value'] = db()->escape($value);
        $key = db()->escape($key);
        $rs = db()->select('*', '[+prefix+]system_settings', "setting_name='{$key}'");

        if (db()->count($rs) == 0) {
            db()->insert($f, '[+prefix+]system_settings');
            $diff = $modx->db->getAffectedRows();
            if (!$diff) {
                $modx->messageQuit('Error while inserting new option into database.', $modx->db->lastQuery);
                exit();
            }
        } else {
            db()->update($f, '[+prefix+]system_settings', "setting_name='{$key}'");
        }

        $modx->getSettings();
    }

    function mergeInlineFilter($content)
    {
        global $modx;

        if (strpos($content, '[+@') === false) {
            return $content;
        }

        if ($modx->debug) {
            $fstart = $modx->getMicroTime();
        }

        $matches = $modx->getTagsFromContent($content, '[+@', '+]');
        if (!$matches) {
            return $content;
        }

        $replace = [];
        foreach ($matches['1'] as $i => $key) {
            $delim = substr($key, 0, 1);
            switch ($delim) {
                case '"':
                case '`':
                case "'":
                    if (substr_count($key, $delim) == 1) {
                        break;
                    }
                    $key = substr($key, 1);
                    [$body, $remain] = explode($delim, $key, 2);
                    $key = str_replace(':', hash('crc32b', ':'), $body) . $remain;
            }
            if (strpos($key, ':') !== false) {
                [$key, $modifiers] = explode(':', $key, 2);
            } else {
                $modifiers = false;
            }
            if (strpos($key, hash('crc32b', ':')) !== false) {
                $key = str_replace(hash('crc32b', ':'), ':', $key);
            }
            $value = $key;
            if ($modifiers !== false) {
                evo()->loadExtension('MODIFIERS');
                $value = $modx->filter->phxFilter($key, $value, $modifiers);
            }
            $replace[$i] = $value;
        }

        $content = str_replace($matches['0'], $replace, $content);
        if ($modx->debug) {
            $_ = implode(', ', $matches['0']);
            $modx->addLogEntry('$modx->' . __FUNCTION__ . "[{$_}]", $fstart);
        }
        return $content;
    }

    function updateDraft()
    {
        global $modx;

        $now = serverv('REQUEST_TIME', 0) + evo()->config('server_offset_time', 0);

        $rs = db()->select(
            '*',
            '[+prefix+]site_revision',
            sprintf("pub_date!=0 AND pub_date<%s AND status = 'standby'", $now)
        );

        if (!db()->count($rs)) {
            return;
        }

        evo()->loadExtension('REVISION');
        evo()->loadExtension('DocAPI');
        while ($row = db()->getRow($rs)) {
            $draft = $modx->revision->getDraft($row['elmid']);
            $draft['editedon'] = $row['editedon'];
            $draft['editedby'] = $row['editedby'];
            $draft['published'] = 1;

            if ($modx->doc->update($draft, $row['elmid']) !== false) {
                db()->delete(
                    '[+prefix+]site_revision',
                    sprintf('internalKey=%d', $row['internalKey'])
                );
            }else{
                $modx->logEvent(0,
                                3,
                                'Update failed.<br />docid='.$row['elmid'].'<br />draftid='.$row['internalKey'],
                                'Draft update error');
            }
        }
    }

    function setdocumentMap()
    {
        global $modx;

        $fields = 'id, parent';
        $rs = db()->select($fields, '[+prefix+]site_content', 'deleted=0', 'parent, menuindex');
        $modx->documentMap = [];
        while ($row = db()->getRow($rs)) {
            $modx->documentMap[] = [$row['parent'] => $row['id']];
        }
    }

    function setAliasListing()
    {
        global $modx;

        $aliasListingCachePath = MODX_CACHE_PATH . 'aliasListing.siteCache.idx.php';
        if (!$modx->aliasListing && is_file($aliasListingCachePath)) {
            $aliases = include $aliasListingCachePath;
            if ($aliases) {
                $modx->aliasListing = $aliases;
            }
        }

        $documentMapCachePath = MODX_CACHE_PATH . 'documentMap.siteCache.idx.php';
        if (!$modx->documentMap && is_file($documentMapCachePath)) {
            $documentMap = include MODX_CACHE_PATH . 'documentMap.siteCache.idx.php';
            if ($documentMap) {
                $modx->documentMap = $documentMap;
            }
        }
        return false;
    }
}

if (!class_exists('SubParser')) {
    /**
     * @deprecated Use DocumentParser methods directly.
     */
    class SubParser
    {
        use DocumentParserSubParserTrait;
    }
}
