<?php
/*
 * Title: Wordpress
 * Purpose:
 *  	Emulate Wordpress archives using Reflect and Ditto
 * 		No parameters can be set by the user
*/
$showItems=0;
$groupByYears=0;
$tplMonth = '@CODE 
	<li class="reflect_month">
		<a href="[+url+]" title="[+year+]/[+month+]" class="reflect_month_link">[+year+]/[+month+]</a>
		[+wrapper+]
	</li>
';
$monthSortDir='DESC';
?>