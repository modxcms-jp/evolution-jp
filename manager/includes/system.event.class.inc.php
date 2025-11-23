<?php

// SystemEvent Class
class SystemEvent
{
    public $name;
    public $_propagate;
    public $_output;
    public $_globalVariables;
    public $activated;
    public $activePlugin;
    public $params = [];
    public $vars = [];
    public $cm = null;
    public $SystemAlertMsgQueque;
    public $returnedValues;

    public function __construct($name = '')
    {
        $this->_resetEventObject();
        $this->name = $name;
        $this->activePlugin = '';
    }

    // used for displaying a message to the user
    public function alert($msg)
    {
        if ($msg == '' || !is_array($this->SystemAlertMsgQueque)) {
            return;
        }
        $alert = [];
        if ($this->name && $this->activePlugin) {
            $alert[] = sprintf(
                '<div><b>%s</b> - <span style="color:maroon;">%s</span></div>',
                $this->activePlugin,
                $this->name
            );
        }
        $alert[] = sprintf('<div style="margin-left:10px;margin-top:3px;">%s</div>', $msg);
        $this->SystemAlertMsgQueque[] = implode('', $alert);
    }

    // used for rendering an out on the screen
    public function output($msg)
    {
        if (!is_object($this->cm)) {
            return;
        }
        $this->cm->addOutput($msg);
    }

    // get global variables
    public function getGlobalVariable($key)
    {
        if (isset($GLOBALS[$key])) {
            return $GLOBALS[$key];
        }
        return false;
    }

    // set global variables
    public function setGlobalVariable($key, $val, $now = 0)
    {
        if (!isset($GLOBALS[$key])) {
            return false;
        }
        if ($now === 1 || $now === 'now') {
            $GLOBALS[$key] = $val;
        } else {
            $this->_globalVariables[$key] = $val;
        }
        return true;
    }

    // set all global variables
    public function setAllGlobalVariables()
    {
        if (empty($this->_globalVariables)) {
            return false;
        }
        foreach ($this->_globalVariables as $key => $val) {
            $GLOBALS[$key] = $val;
        }
        return true;
    }

    public function stopPropagation()
    {
        $this->_propagate = false;
    }

    public function _resetEventObject()
    {
        unset ($this->returnedValues);
        $this->_output = '';
        $this->_globalVariables = [];
        $this->_propagate = true;
        $this->activated = false;
        $this->params = [];
    }

    public function getParam($key, $default = null)
    {
        if (!isset($this->params[$key])) {
            return $default;
        }
        if (strtolower($this->params[$key]) === 'false') {
            $this->params[$key] = false;
        }
        return $this->params[$key];
    }

    public function setParam($key, $value)
    {
        $this->params[$key] = $value;
    }

    public function param($key, $default = null)
    {
        return array_get($this->params ?? [], $key, $default);
    }
}
