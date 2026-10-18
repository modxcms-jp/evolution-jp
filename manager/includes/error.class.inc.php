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
            1 => lang('error_occurred'),
            2 => lang('error_id_not_passed'),
            3 => lang('error_no_privileges'),
            5 => lang('error_document_locked'),
            6 => lang('error_too_many_results'),
            7 => lang('error_no_results'),
            9 => lang('error_logging'),
            10 => lang('error_optimize_table_not_set'),
            13 => lang('error_no_recipient_user'),
            14 => lang('error_no_recipient_group'),

            100 => lang('error_double_action'),
            601 => lang('error_id_not_passed'),
            602 => lang('error_new_parent_not_set'),
            900 => lang('error_unknown_user'),
            901 => lang('error_wrong_password'),
            902 => lang('error_login_blocked'),
            905 => lang('error_captcha_invalid')
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
        // 値は <script> 内の JS リテラルとして埋め込む（js_literal()）。
        // jAlert はメッセージを HTML として表示するので先に HTML エスケープする。タイトルは文字列として表示するのでしない。
        // 埋め込んだ値の中が再び置換されないよう、parseText() ではなく strtr() で一度だけ置換する
        echo strtr(
            file_get_contents(MODX_MANAGER_PATH . 'media/style/common/dump_error.tpl'),
            [
                '[+message+]' => js_literal(hsc((string)$this->errormessage)),
                '[+warning+]' => js_literal((string)lang('warning')),
                '[+url+]' => js_literal((string)$this->prev())
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

        $previous = (string)sessionv('previous_request_uri', '');
        if ($previous === '') {
            return 'index.php?a=2';
        }

        if (preg_match('/[&?]count_attempts/', $previous)) {
            return $previous;
        }

        return sprintf(
            '%s%scount_attempts=1',
            $previous,
            strpos($previous, '?') === false ? '?' : '&'
        );
    }

    private function getError()
    {
        return $this->errorcode;
    }
}
