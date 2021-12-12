<?php
/** @var MODIFIERS $value */
/** @var MODIFIERS $opt */
$pattern = '/<img[\s\n]+[^>]*src=[\s\n]*"([^"]+\.(jpg|jpeg|png|gif))"[^>]*>/i';
preg_match_all($pattern, $value, $images);
if ($opt === '') {
    if ($images[1][0]) {
        return $images[1][0];
    }

    return '';
}

if (3 <= strlen($opt) && preg_match('/^[jpg].*$/i', $opt)) {
    $_ = explode(',', $opt);
    foreach ($images[2] as $i => $ext) {
        if (in_array($ext, $_, true)) {
            return $images[1][$i];
        }
    }

    return '';
}

foreach ($images[0] as $i => $image) {
    if (preg_match($opt, $image)) {
        return $images[1][$i];
    }
}

return '';
