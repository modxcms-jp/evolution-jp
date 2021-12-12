<?php
function ProcessTVCommand($value, $name = '', $docid = '', $src = 'docform')
{
    global $modx;
    return $modx->ProcessTVCommand($value, $name, $docid, $src);
}

function ParseCommand($binding_string)
{
    global $modx;
    return $modx->splitTVCommand($binding_string);
}

function getExtention($str)
{
    global $modx;
    return $modx->getExtention($str);
}
