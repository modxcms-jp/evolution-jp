<?php

// this is the old error handler. Here for legacy, until i replace all the old errors.
class errorHandler
{

    public $errorcode = null;
    public $errors = [];
    private $errormessage;

    public function __construct()
    {
        $this->errors = [
            0 => lang('No errors occured.'),
            1 => lang('An error occured!'),
            2 => lang("Document's ID not passed in request!"),
            3 => lang("You don't have enough privileges for this action!"),
            4 => lang('ID passed in request is NaN!'),
            5 => lang('The document is locked!'),
            6 => lang('Too many results returned from database!'),
            7 => lang('Not enough/ no results returned from database!'),
            8 => lang("Couldn't find parent document's name!"),
            9 => lang('Logging error!'),
            10 => lang('Table to optimise not found in request!'),
            11 => lang('No settings found in request!'),
            12 => lang('The document must have a title!'),
            13 => lang('No user selected as recipient of this message!'),
            14 => lang('No group selected as recipient of this message!'),
            15 => lang('The document was not found!'),

            100 => lang('Double action (GET & POST) posted!'),
            600 => lang("Document cannot be it's own parent!"),
            601 => lang("Document's ID not passed in request!"),
            602 => lang('New parent not set in request!'),
            900 => lang("don't know the user!"), // don't know the user!
            901 => lang('wrong password!'), // wrong password!
            902 => lang('Due to too many failed logins, you have been blocked!'),
            903 => lang('You are blocked and cannot log in!'),
            904 => lang('You are blocked and cannot log in! Please try again later.'),
            905 => lang("The security code you entered didn't validate! Please try to login again!")
        ];
    }

    public function setError($errorcode, $message = '')
    {
        $this->errorcode = $errorcode;
        if ($message) {
            $this->errormessage = $message;
            return;
        }
        $this->errormessage = evo()->array_get($this->errors, $errorcode, $errorcode);
    }

    public function hasError()
    {
        return $this->errorcode;
    }

    public function dumpError()
    {
        include_once MODX_MANAGER_PATH . 'actions/header.inc.php';
        echo evo()->parseText(
            file_get_contents(MODX_MANAGER_PATH . 'media/style/_system/dump_error.tpl'),
            [
                'message' => db()->escape($this->errormessage),
                'warning' => lang('warning'),
                'url' => $this->prev()
            ]
        );
        include_once MODX_MANAGER_PATH . 'actions/footer.inc.php';
        exit;
    }

    private function prev()
    {
        if (getv('count_attempts')) {
            return 'index.php?a=2';
        }

        if (preg_match('/[&?]count_attempts/', sessionv('previous_request_uri'))) {
            return sessionv('previous_request_uri');
        }

        return sprintf(
            '%s%scount_attempts=1',
            sessionv('previous_request_uri'),
            strpos(sessionv('previous_request_uri'), '?') === false ? '?' : '&'
        );
    }

    private function getError()
    {
        return $this->errorcode;
    }
}
