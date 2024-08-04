<?php

class udperms
{
    var $document;
    var $duplicateDoc = false;

    function checkPermissions()
    {
        global $modx;
        return $modx->checkPermissions($this->document, $this->duplicateDoc);
    }
}
