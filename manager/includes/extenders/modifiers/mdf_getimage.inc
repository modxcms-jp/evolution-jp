<?php
$pattern = '/<img[\s\n]+src=[\s\n]*"([^"]+\.(jpg|jpeg|png|gif))"[^>]+>/i';
preg_match_all($pattern , $value , $images);
$value = '';
if($opt==='')
{
    if($images[1][0])  $value = $images[1][0];
    else               $value = '';
}
else
{
    foreach($images[0] as $i=>$image)
    {
        if(strpos($image,$opt)!==false) $value = $images[1][$i];
    }
    break;
}

return $value;
