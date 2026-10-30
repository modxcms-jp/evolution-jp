<?php
/** @var string $value */
/** @var string $opt */
$wrapat = max((int)$opt, 0) ?: 70;
return preg_replace_callback('~(\b\w+\b)~', function ($m) use ($wrapat) {
    return wordwrap($m[1], $wrapat, ' ', true);
}, $value);
