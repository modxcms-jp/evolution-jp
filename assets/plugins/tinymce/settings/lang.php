<?php
function get_mce_lang($lang)
{
	switch($lang)
	{
		case 'russian-utf8' : $lc='ru'; break;
		case 'japanese-utf8':
		case 'japanese-euc' : $lc = 'ja'; break;
		default             : $lc = 'en';
	}
	return $lc;
}
