<?php
$pattern = '/<img[\s\n]+[^>]*src=[\s\n]*"([^"]+\.(jpg|jpeg|png|gif))"[^>]*>/i';
preg_match_all($pattern , $value , $images);
if($opt==='')
{
    if($images[1][0])  return $images[1][0];
    else               return '';
} elseif(3<=strlen($opt) && preg_match('/^[jpg].*$/i',$opt)) {
    $_ = explode(',', $opt);
    foreach($images[2] as $i=>$ext) {
        if(in_array($ext,$_)) return $images[1][$i];
    }
} else {
    foreach($images[0] as $i=>$image) {
        if(preg_match($opt,$image)) return $images[1][$i];
    }
}

return '';
