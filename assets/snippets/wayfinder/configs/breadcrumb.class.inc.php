<?php

class WFBC
{
    function __construct()
    {
    }

    function fetch($tpl)
    {
        global $modx;
        if (strpos($tpl, '@FILE') === 0) {
            $file_path = evo()->findBindFile(substr($tpl, 6));
            if (!$file_path) {
                $template = false;
            } elseif (evo()->getExtention($file_path) === '.php') {
                $template = 'Could not retrieve PHP file.';
            } else {
                $template = file_get_contents($file_path);
            }
        } elseif (strpos($tpl, '@CODE') === 0) {
            $template = substr($tpl, 6);
        } elseif ($modx->getChunk($tpl) != '') {
            $template = $modx->getChunk($tpl);
        } else {
            $template = $tpl;
        }
        return $template;
    }
}
