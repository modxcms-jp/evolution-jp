<?php

class logHandler
{
    // Single variable for a log entry
    public $entry = [];

    public function __construct()
    {
    }

    function logError($msg)
    {
        include_once MODX_CORE_PATH . 'error.class.inc.php';
        $e = new errorHandler;
        alert()->setError(9, "Logging error: " . $msg);
        alert()->dumpError();
    }

    public function initAndWriteLog(
        $msg = '',
        $internalKey = '',
        $username = '',
        $action = '',
        $itemid = '',
        $itemname = ''
    )
    {
        $this->setEntry($msg, $internalKey, $username, $itemname);
        $this->writeToLog(evo()->input_any('a', 0), evo()->input_any('id', 'x'));
    }

    public function writeToLog($action_id = 0, $item_id = 'x')
    {
        global $modx;

        if ($this->entry['internalKey'] == '') {
            $this->logError('internalKey not set.');
            return;
        }
        if ($this->entry['msg'] == '') {
            include_once(MODX_CORE_PATH . 'actionlist.inc.php');
            $this->entry['msg'] = getAction($action_id, $this->entry['itemId']);
            if ($this->entry['msg'] == '') {
                $this->logError("couldn't find message to write to log.");
                return;
            }
        }

        $insert_id = db()->insert(
            db()->escape([
                'timestamp' => time(),
                'internalKey' => $this->entry['internalKey'],
                'username' => $this->entry['username'],
                'action' => $action_id,
                'itemid' => $item_id,
                'itemname' => $this->entry['itemName'],
                'message' => $this->entry['msg']
            ])
            , '[+prefix+]manager_log'
        );
        if (!$insert_id) {
            $this->logError("Couldn't save log to table! " . db()->getLastError());
            return;
        }

        if (($insert_id % (int)$modx->conf_var('manager_log_trim', 100)) !== 0) {
            return;
        }

        $modx->rotate_log(
            'manager_log'
            , (int)$modx->conf_var('manager_log_limit', 2000)
            , (int)$modx->conf_var('manager_log_trim', 100)
        );
    }

    private function setEntry($msg = '', $internalKey = '', $username = '', $itemname = '')
    {
        global $modx;

        $this->entry['msg'] = $msg;    // writes testmessage to the object

        // User Credentials
        if ($internalKey != '') {
            $this->entry['internalKey'] = $internalKey;
        } else {
            $this->entry['internalKey'] = evo()->getLoginUserID();
        }
        if ($username != '') {
            $this->entry['username'] = $username;
        } else {
            $this->entry['username'] = $modx->getLoginUserName();
        }

        if ($itemname != '') {
            $this->entry['itemName'] = $itemname;
        } else {
            $this->entry['itemName'] = sessionv('itemname', '-');
        }    // writes the id to the object
    }
}
